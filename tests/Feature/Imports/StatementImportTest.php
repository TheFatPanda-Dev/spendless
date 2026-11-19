<?php

namespace Tests\Feature\Imports;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StatementImportTest extends TestCase
{
    use RefreshDatabase;

    private const BARCLAYS = "Number,Date,Account,Amount,Subcategory,Memo\n"
        .",01/10/2026,20-00-00 12345678,-23.10,PAYMENT,SAINSBURYS S/MKT\n"
        .",02/10/2026,20-00-00 12345678,2500.00,DIRECTDEP,ACME LTD SALARY\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_statement_is_previewed_then_imported_into_a_new_account(): void
    {
        $user = User::factory()->create();
        $groceries = Category::query()->create(['user_id' => $user->id, 'name' => 'Groceries', 'type' => 'expense', 'icon' => 'shopping-basket', 'color' => 'green']);

        $token = $this->upload($user, 'barclays.csv', self::BARCLAYS);

        $this->actingAs($user)->get(route('imports.show', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('imports/preview')
                ->where('statement.format', 'barclays')
                ->where('statement.bank', 'Barclays')
                ->where('summary.new', 2)
                ->where('summary.updated', 0)
                ->where('target', 'new')
                ->has('rows', 2));

        $this->actingAs($user)->post(route('imports.confirm', $token), [
            'target' => 'new',
            'name' => 'Barclays Current',
            'institution' => 'Barclays',
            'currency' => 'GBP',
            'kind' => 'current',
            'balance' => '1000.00',
        ])->assertRedirect()->assertSessionHas('success', 'Imported 2 new transactions.');

        $account = BankAccount::query()->sole();
        $this->assertSame(BankConnection::PROVIDER_IMPORT, $account->connection->provider);
        $this->assertSame('Barclays', $account->connection->institution_name);
        $this->assertSame('GBP', $account->currency);
        $this->assertSame(1000.0, (float) $account->balances_encrypted['current']);
        $this->assertSame(2, $account->transactions()->count());
        $this->assertSame($groceries->id, $account->transactions()->where('amount', '<', 0)->sole()->category_id);
        $this->assertDatabaseHas('bank_statement_imports', ['bank_account_id' => $account->id, 'rows_added' => 2, 'format' => 'barclays']);
        Storage::disk('local')->assertDirectoryEmpty('imports/'.$user->id);
    }

    public function test_overlapping_statements_only_add_new_transactions(): void
    {
        $user = User::factory()->create();
        $account = $this->importNew($user, 'barclays.csv', self::BARCLAYS, balance: '1000');

        $token = $this->upload($user, 'barclays-2.csv', self::BARCLAYS.",03/10/2026,20-00-00 12345678,-5.00,PAYMENT,PRET A MANGER\n", $account);

        $this->actingAs($user)->get(route('imports.show', $token))
            ->assertInertia(fn (Assert $page) => $page
                ->where('target', (string) $account->id)
                ->where('summary.new', 1)
                ->where('summary.updated', 2));

        $this->confirmInto($user, $token, $account)->assertSessionHas('success', 'Imported 1 new transaction (2 already there).');

        $this->assertSame(3, $account->transactions()->count());
        // No balance column: the new transaction is added to the known balance.
        $this->assertSame(995.0, (float) $account->fresh()->balances_encrypted['current']);
    }

    public function test_reimport_keeps_categories_chosen_by_hand(): void
    {
        $user = User::factory()->create();
        $account = $this->importNew($user, 'barclays.csv', self::BARCLAYS, balance: '0');
        $fun = Category::query()->create(['user_id' => $user->id, 'name' => 'Fun', 'type' => 'expense', 'icon' => 'party-popper', 'color' => 'pink']);
        $account->transactions()->where('amount', '<', 0)->update(['category_id' => $fun->id, 'category_manually_set' => true]);

        $this->confirmInto($user, $this->upload($user, 'again.csv', self::BARCLAYS, $account), $account);

        $this->assertSame($fun->id, $account->transactions()->where('amount', '<', 0)->sole()->category_id);
    }

    public function test_revolut_pending_payments_complete_and_cancelled_ones_disappear(): void
    {
        $user = User::factory()->create();
        $header = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n";

        $account = $this->importNew($user, 'revolut-1.csv', $header
            ."CARD_PAYMENT,Current,2026-10-01 09:15:02,2026-10-01 10:00:00,Tesco,-12.40,0.00,GBP,COMPLETED,987.60\n"
            ."CARD_PAYMENT,Current,2026-10-03 18:20:00,,Pret A Manger,-4.50,0.00,GBP,PENDING,\n"
            ."CARD_PAYMENT,Current,2026-10-03 19:00:00,,Online shop,-30.00,0.00,GBP,PENDING,\n");

        $this->assertSame(3, $account->transactions()->count());
        $this->assertSame(2, $account->transactions()->where('pending', true)->count());

        $this->confirmInto($user, $this->upload($user, 'revolut-2.csv', $header
            ."CARD_PAYMENT,Current,2026-10-01 09:15:02,2026-10-01 10:00:00,Tesco,-12.40,0.00,GBP,COMPLETED,987.60\n"
            ."CARD_PAYMENT,Current,2026-10-03 18:20:00,2026-10-04 08:00:00,Pret A Manger,-4.55,0.00,GBP,COMPLETED,983.05\n"
            ."CARD_PAYMENT,Current,2026-10-03 19:00:00,,Online shop,-30.00,0.00,GBP,REVERTED,\n", $account), $account);

        $this->assertSame(2, $account->transactions()->count());
        $pret = $account->transactions()->where('merchant_name', 'Pret A Manger')->sole();
        $this->assertSame('Card payment', $pret->name, "Revolut's type is the description");
        $this->assertFalse($pret->pending);
        $this->assertSame('-4.55', $pret->amount, 'Settled amount replaces the pending one');
        $this->assertSame(983.05, (float) $account->fresh()->balances_encrypted['current']);
    }

    public function test_an_older_statement_never_rolls_the_balance_back(): void
    {
        $user = User::factory()->create();
        $account = $this->importNew($user, 'recent.ofx', $this->ofx('20261017', '1500.00', [['A1', '20261015', '-10.00']]));

        $this->confirmInto($user, $this->upload($user, 'old.ofx', $this->ofx('20260930', '900.00', [['A0', '20260920', '-20.00']]), $account), $account);

        $account->refresh();
        $this->assertSame(1500.0, (float) $account->balances_encrypted['current']);
        $this->assertSame('2026-10-17', $account->balances_encrypted['as_of']);
        $this->assertSame(2, $account->transactions()->count());
    }

    public function test_column_mapping_is_remembered_for_the_account(): void
    {
        $user = User::factory()->create();
        $csv = "When,What,How much\n01/10/2026,Coffee,-2.50\n02/10/2026,Refund,10.00\n";
        $token = $this->upload($user, 'odd.csv', $csv);

        $this->actingAs($user)->get(route('imports.show', $token))
            ->assertInertia(fn (Assert $page) => $page->where('statement.needs_mapping', true));

        $this->actingAs($user)->patch(route('imports.mapping', $token), [
            'header_row' => 0, 'date' => 0, 'date_format' => 'd/m/Y', 'description' => [1], 'amount' => 2, 'flip_sign' => false,
        ])->assertRedirect(route('imports.show', $token));

        $this->actingAs($user)->get(route('imports.show', $token))
            ->assertInertia(fn (Assert $page) => $page->where('statement.needs_mapping', false)->where('summary.new', 2));

        $this->confirmNew($user, $token);
        $account = BankAccount::query()->sole();

        $next = $this->upload($user, 'odd-2.csv', $csv."03/10/2026,Lunch,-8.00\n", $account);

        $this->actingAs($user)->get(route('imports.show', $next))
            ->assertInertia(fn (Assert $page) => $page
                ->where('statement.needs_mapping', false)
                ->where('summary.new', 1)
                ->where('summary.updated', 2));
    }

    public function test_uploads_are_private_to_their_owner(): void
    {
        $owner = User::factory()->create();
        $token = $this->upload($owner, 'barclays.csv', self::BARCLAYS);

        $this->actingAs(User::factory()->create())->get(route('imports.show', $token))->assertNotFound();
        $this->actingAs(User::factory()->create())->post(route('imports.confirm', $token), ['target' => 'new'])->assertNotFound();
    }

    public function test_cannot_import_into_someone_elses_account(): void
    {
        $other = User::factory()->create();
        $otherAccount = $this->importNew($other, 'barclays.csv', self::BARCLAYS, balance: '0');
        $user = User::factory()->create();
        $token = $this->upload($user, 'barclays.csv', self::BARCLAYS);

        $this->actingAs($user)->post(route('imports.confirm', $token), ['target' => (string) $otherAccount->id])->assertForbidden();
        $this->assertSame(2, BankTransaction::query()->count());
    }

    public function test_unsupported_files_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('statement.pdf', '%PDF-1.4'),
        ])->assertSessionHasErrors('file');
    }

    private function upload(User $user, string $name, string $contents, ?BankAccount $account = null): string
    {
        $response = $this->actingAs($user)->post(route('imports.store'), [
            'file' => UploadedFile::fake()->createWithContent($name, $contents),
            'account_id' => $account?->id,
        ]);

        $response->assertRedirect();

        return basename((string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
    }

    private function importNew(User $user, string $name, string $contents, ?string $balance = null): BankAccount
    {
        $this->confirmNew($user, $this->upload($user, $name, $contents), $balance);

        return BankAccount::query()->latest('id')->firstOrFail();
    }

    private function confirmNew(User $user, string $token, ?string $balance = null): TestResponse
    {
        return $this->actingAs($user)->post(route('imports.confirm', $token), [
            'target' => 'new',
            'name' => 'Imported',
            'currency' => 'GBP',
            'kind' => 'current',
            'balance' => $balance,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function confirmInto(User $user, string $token, BankAccount $account): TestResponse
    {
        return $this->actingAs($user)->post(route('imports.confirm', $token), [
            'target' => (string) $account->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    /**
     * @param  list<array{string, string, string}>  $transactions  [fitid, date, amount]
     */
    private function ofx(string $balanceDate, string $balance, array $transactions): string
    {
        $rows = implode("\n", array_map(
            fn (array $t): string => "<STMTTRN><TRNTYPE>DEBIT<DTPOSTED>{$t[1]}<TRNAMT>{$t[2]}<FITID>{$t[0]}<NAME>Shop {$t[0]}</STMTTRN>",
            $transactions,
        ));

        return "OFXHEADER:100\nDATA:OFXSGML\n\n<OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><CURDEF>GBP<BANKACCTFROM><ACCTID>12345678</BANKACCTFROM>\n"
            ."<BANKTRANLIST>\n{$rows}\n</BANKTRANLIST>\n<LEDGERBAL><BALAMT>{$balance}<DTASOF>{$balanceDate}</LEDGERBAL></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>";
    }
}
