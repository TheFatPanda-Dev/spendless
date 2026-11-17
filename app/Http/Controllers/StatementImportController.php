<?php

namespace App\Http\Controllers;

use App\Actions\Wallets\CreateImportedAccount;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\User;
use App\Services\Imports\ImportValueParser;
use App\Services\Imports\ParsedStatement;
use App\Services\Imports\StatementImporter;
use App\Services\Imports\StatementParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Upload a bank statement file → preview what changes → confirm.
 */
class StatementImportController extends Controller
{
    private const EXTENSIONS = ['csv', 'tsv', 'txt', 'ofx', 'qfx', 'xlsx'];

    private const UPLOAD_TTL_MINUTES = 120;

    public function create(Request $request): Response
    {
        $accounts = $this->importAccounts($request->user());

        return Inertia::render('imports/create', [
            'accounts' => $accounts->map(fn (BankAccount $account): array => $this->accountSummary($account))->values(),
            'selectedAccountId' => $accounts->firstWhere('id', $request->integer('account'))?->id,
            'extensions' => self::EXTENSIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', function (string $attribute, mixed $file, \Closure $fail): void {
                $extension = strtolower((string) $file->getClientOriginalExtension());

                if (! in_array($extension, self::EXTENSIONS, true)) {
                    $fail('Upload a CSV, OFX/QFX, TXT or XLSX file.');
                }
            }],
            'account_id' => ['nullable', 'integer'],
        ]);

        $accountId = isset($validated['account_id'])
            ? $this->importAccounts($request->user())->firstWhere('id', (int) $validated['account_id'])?->id
            : null;

        $token = Str::random(40);
        $file = $request->file('file');
        $path = $file->storeAs('imports/'.$request->user()->id, $token.'.upload', 'local');

        Cache::put($this->cacheKey($token), [
            'user_id' => $request->user()->id,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'account_id' => $accountId,
            'mapping' => null,
        ], now()->addMinutes(self::UPLOAD_TTL_MINUTES));

        return to_route('imports.show', $token);
    }

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $upload = $this->upload($request, $token);
        $accounts = $this->importAccounts($request->user());
        $target = $request->query('account', $upload['account_id'] !== null ? (string) $upload['account_id'] : 'new');
        $account = $target === 'new' ? null : $accounts->firstWhere('id', (int) $target);

        try {
            $statement = $this->parse($upload, $account);
        } catch (RuntimeException $exception) {
            $this->forget($token, $upload);

            return to_route('imports.create')->with('error', $exception->getMessage());
        }

        $currency = $account?->currency ?? $statement->currency ?? 'GBP';
        $plan = app(StatementImporter::class)->plan($statement, $account, $currency);

        return Inertia::render('imports/preview', [
            'token' => $token,
            'filename' => $upload['filename'],
            'statement' => [
                'format' => $statement->format,
                'format_label' => $statement->formatLabel,
                'bank' => $statement->bank,
                'account_hint' => $statement->accountHint,
                'currency' => $statement->currency,
                'closing_balance' => $statement->closingBalance,
                'balance_date' => $statement->balanceDate,
                'warnings' => $statement->warnings,
                'needs_mapping' => $statement->needsMapping,
                'is_generic' => $statement->format === 'csv',
                'columns' => $statement->columns,
                'sample_rows' => $statement->sampleRows,
                'mapping' => $statement->mapping,
            ],
            'summary' => [
                'total' => count($plan->records),
                'new' => $plan->newCount(),
                'updated' => $plan->updatedCount(),
                'removed' => $plan->removedCount(),
                'skipped' => $plan->skipped,
                'date_from' => $plan->dateFrom,
                'date_to' => $plan->dateTo,
                'new_total' => $plan->newTotal(),
                'currency' => $currency,
            ],
            'rows' => $plan->sample(),
            'target' => $account !== null ? (string) $account->id : 'new',
            'accounts' => $accounts->map(fn (BankAccount $item): array => $this->accountSummary($item))->values(),
            'suggestion' => [
                'name' => trim(implode(' ', array_filter([$statement->bank, $statement->accountHint]))) ?: 'Imported account',
                'institution' => $statement->bank,
                'currency' => $statement->currency ?? 'GBP',
            ],
            'dateFormats' => ['auto', ...ImportValueParser::DATE_FORMATS, ...ImportValueParser::MANUAL_DATE_FORMATS],
        ]);
    }

    public function updateMapping(Request $request, string $token): RedirectResponse
    {
        $upload = $this->upload($request, $token);

        $mapping = $request->validate([
            'header_row' => ['required', 'integer', 'min:0', 'max:50'],
            'date' => ['required', 'integer', 'min:0'],
            'date_format' => ['required', 'string', Rule::in(['auto', ...ImportValueParser::DATE_FORMATS, ...ImportValueParser::MANUAL_DATE_FORMATS])],
            'description' => ['required', 'array', 'min:1'],
            'description.*' => ['integer', 'min:0'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'paid_out' => ['nullable', 'integer', 'min:0'],
            'paid_in' => ['nullable', 'integer', 'min:0'],
            'balance' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'integer', 'min:0'],
            'flip_sign' => ['boolean'],
            'account' => ['nullable', 'string'],
        ]);

        if (($mapping['amount'] ?? null) === null && ($mapping['paid_out'] ?? null) === null && ($mapping['paid_in'] ?? null) === null) {
            return back()->withErrors(['amount' => 'Choose the amount column, or the paid in / paid out columns.']);
        }

        $upload['mapping'] = [...Arr::except($mapping, ['account']), 'flip_sign' => (bool) ($mapping['flip_sign'] ?? false)];
        Cache::put($this->cacheKey($token), $upload, now()->addMinutes(self::UPLOAD_TTL_MINUTES));

        return to_route('imports.show', ['token' => $token, 'account' => $mapping['account'] ?? null]);
    }

    public function confirm(
        Request $request,
        string $token,
        StatementImporter $importer,
        CreateImportedAccount $createImportedAccount,
    ): RedirectResponse {
        $upload = $this->upload($request, $token);
        $user = $request->user();

        $validated = $request->validate([
            'target' => ['required', 'string'],
            'name' => ['required_if:target,new', 'nullable', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:120'],
            'currency' => ['required_if:target,new', 'nullable', 'string', 'size:3'],
            'kind' => ['required_if:target,new', 'nullable', Rule::in(CreateImportedAccount::KINDS)],
            'balance' => ['nullable', 'numeric', 'between:-999999999999.99,999999999999.99'],
        ]);

        $account = $validated['target'] === 'new'
            ? null
            : $this->importAccounts($user)->firstWhere('id', (int) $validated['target']);

        if ($validated['target'] !== 'new' && $account === null) {
            abort(403);
        }

        try {
            $statement = $this->parse($upload, $account);
        } catch (RuntimeException $exception) {
            $this->forget($token, $upload);

            return to_route('imports.create')->with('error', $exception->getMessage());
        }

        if ($statement->needsMapping) {
            return back()->withErrors(['mapping' => 'Tell SpendLess which columns hold the date, description and amount first.']);
        }

        $account ??= $createImportedAccount($user, [
            'name' => (string) $validated['name'],
            'currency' => strtoupper((string) $validated['currency']),
            'kind' => (string) $validated['kind'],
            'institution' => $validated['institution'] ?? $statement->bank,
        ]);

        $plan = $importer->plan($statement, $account, (string) $account->currency);
        $import = $importer->apply(
            $plan,
            $statement,
            $account,
            $user,
            $upload['filename'],
            isset($validated['balance']) ? (float) $validated['balance'] : null,
        );

        $this->rememberMapping($account, $statement);
        $this->forget($token, $upload);

        return to_route('accounts.show', [
            'bankAccount' => $account,
            'start_date' => ($import->date_to ?? now())->copy()->startOfMonth()->toDateString(),
            'end_date' => ($import->date_to ?? now())->copy()->endOfMonth()->toDateString(),
        ])->with('success', sprintf(
            'Imported %d new transaction%s%s.',
            $import->rows_added,
            $import->rows_added === 1 ? '' : 's',
            $import->rows_updated > 0 ? sprintf(' (%d already there)', $import->rows_updated) : '',
        ));
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $this->forget($token, $this->upload($request, $token));

        return to_route('imports.create');
    }

    /**
     * @param  array{path: string, mapping: array<string, mixed>|null}  $upload
     */
    private function parse(array $upload, ?BankAccount $account): ParsedStatement
    {
        $path = Storage::disk('local')->path($upload['path']);
        $statement = StatementParser::parse($path, $upload['mapping']);

        // A layout this account was imported with before: reuse its column mapping.
        if ($upload['mapping'] === null && $statement->format === 'csv' && $statement->layoutSignature !== null && $account !== null) {
            $saved = Arr::get($account->connection?->metadata ?? [], 'csv_mappings.'.$statement->layoutSignature);

            if (is_array($saved)) {
                $statement = StatementParser::parse($path, $saved);
            }
        }

        return $statement;
    }

    private function rememberMapping(BankAccount $account, ParsedStatement $statement): void
    {
        $connection = $account->connection;

        if ($connection === null || $statement->format !== 'csv' || $statement->mapping === null || $statement->layoutSignature === null) {
            return;
        }

        $metadata = $connection->metadata ?? [];
        $metadata['csv_mappings'][$statement->layoutSignature] = $statement->mapping;
        $connection->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * @return array{user_id: int, path: string, filename: string, account_id: int|null, mapping: array<string, mixed>|null}
     */
    private function upload(Request $request, string $token): array
    {
        $upload = Cache::get($this->cacheKey($token));

        abort_unless(is_array($upload) && (int) $upload['user_id'] === $request->user()->id, 404, 'This upload has expired. Please upload the file again.');

        return $upload;
    }

    /**
     * @param  array{path: string}  $upload
     */
    private function forget(string $token, array $upload): void
    {
        Storage::disk('local')->delete($upload['path']);
        Cache::forget($this->cacheKey($token));
    }

    private function cacheKey(string $token): string
    {
        return 'statement-import:'.$token;
    }

    /**
     * @return Collection<int, BankAccount>
     */
    private function importAccounts(User $user): Collection
    {
        return BankAccount::query()
            ->whereIn('bank_connection_id', $user->bankConnections()
                ->where('provider', BankConnection::PROVIDER_IMPORT)
                ->select('id'))
            ->with(['connection', 'statementImports' => fn ($query) => $query->latest()->limit(1)])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function accountSummary(BankAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->display_name ?: $account->name,
            'institution' => $account->connection?->institution_name,
            'currency' => $account->currency,
            'kind' => $account->subtype,
            'last_import' => $account->statementImports->first()?->toSummaryArray(),
        ];
    }
}
