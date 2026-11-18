<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Tag;
use App\Models\TagRule;
use App\Services\Tags\TagRules;
use App\Support\Text\SearchText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Tag new transactions from this account / whose payee contains this text."
 */
class TagRuleController extends Controller
{
    public function store(Request $request, Tag $tag, TagRules $tagRules): RedirectResponse
    {
        abort_unless($tag->user_id === $request->user()->id, 404);

        $accountIds = BankAccount::query()
            ->whereIn('bank_connection_id', $request->user()->bankConnections()->select('id'))
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'bank_account_id' => ['nullable', 'integer', Rule::in($accountIds)],
            'contains' => ['nullable', 'string', 'max:120'],
            'apply_existing' => ['boolean'],
        ]);
        $contains = trim((string) ($validated['contains'] ?? ''));
        $accountId = $validated['bank_account_id'] ?? null;

        if ($accountId === null && SearchText::normalize($contains) === '') {
            throw ValidationException::withMessages([
                'contains' => 'Choose an account, or enter text the payee or description contains.',
            ]);
        }

        $rule = $tag->rules()->create([
            'user_id' => $request->user()->id,
            'bank_account_id' => $accountId,
            'contains' => $contains === '' ? null : $contains,
        ]);

        $message = 'Rule added. New transactions that match will be tagged automatically.';

        if ($request->boolean('apply_existing')) {
            $added = $tagRules->applyRule($rule->setRelation('user', $request->user()));
            $message = sprintf('Rule added and %d existing transaction%s tagged.', $added, $added === 1 ? '' : 's');
        }

        return back()->with('success', $message);
    }

    public function destroy(Request $request, Tag $tag, TagRule $rule): RedirectResponse
    {
        abort_unless($tag->user_id === $request->user()->id, 404);

        $rule->delete();

        return back()->with('success', 'Rule removed. Transactions it already tagged keep the tag.');
    }
}
