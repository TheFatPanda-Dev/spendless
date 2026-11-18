<?php

namespace App\Http\Controllers;

use App\Jobs\SyncEnableBankingConnectionJob;
use App\Jobs\SyncIbkrConnectionJob;
use App\Jobs\SyncPlaidConnectionJob;
use App\Models\BankConnection;
use App\Services\Banking\BankActivityLogger;
use App\Services\EnableBanking\PsuHeaders;
use App\Services\Plaid\PlaidUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Sync all" / "Sync now". The user is present, so the bank is told so via PSU headers
 * and these refreshes don't use up the 4 unattended syncs a day.
 */
class WalletBankConnectionController extends Controller
{
    /** Ignore repeated clicks: a connection synced this recently is skipped. */
    private const COOLDOWN_SECONDS = 60;

    public function refreshAll(Request $request, BankActivityLogger $activityLogger): JsonResponse
    {
        $connections = $request->user()
            ->bankConnections()
            ->whereIn('provider', BankConnection::SYNCED_PROVIDERS)
            ->whereIn('status', [BankConnection::STATUS_CONNECTED, BankConnection::STATUS_SYNC_FAILED])
            ->get();

        $queued = $connections->filter(fn (BankConnection $connection): bool => $this->queueSync($request, $connection));

        $activityLogger->log($request->user(), 'sync_all_requested', ['connections' => $queued->count()]);

        return response()->json([
            'queued' => $queued->count(),
            'message' => $queued->isEmpty()
                ? 'Everything is already up to date.'
                : 'Syncing your banks…',
        ]);
    }

    public function refreshConnection(Request $request, BankConnection $bankConnection): JsonResponse
    {
        Gate::authorize('update', $bankConnection);

        abort_unless(
            in_array($bankConnection->provider, BankConnection::SYNCED_PROVIDERS, true)
                && in_array($bankConnection->status, [BankConnection::STATUS_CONNECTED, BankConnection::STATUS_SYNC_FAILED], true),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $queued = $this->queueSync($request, $bankConnection);

        return response()->json([
            'queued' => $queued ? 1 : 0,
            'message' => match (true) {
                $queued => 'Syncing…',
                $bankConnection->provider === BankConnection::PROVIDER_PLAID && PlaidUsage::exhausted(userPresent: true) => "Plaid's free allowance is used up, so this bank can't sync.",
                $bankConnection->provider === BankConnection::PROVIDER_PLAID => sprintf(
                    'To save Plaid calls, a UK bank syncs at most once every %d minutes.',
                    $this->plaidCooldownMinutes(),
                ),
                default => 'This bank was synced a moment ago.',
            },
        ]);
    }

    private function queueSync(Request $request, BankConnection $connection): bool
    {
        $isPlaid = $connection->provider === BankConnection::PROVIDER_PLAID;
        $cooldown = $isPlaid ? $this->plaidCooldownMinutes() * 60 : self::COOLDOWN_SECONDS;

        if ($connection->last_synced_at?->isAfter(now()->subSeconds($cooldown))) {
            return false;
        }

        // Each Plaid sync costs calls from the free allowance.
        if ($isPlaid && PlaidUsage::exhausted(userPresent: true)) {
            return false;
        }

        $connection->forceFill(['status' => BankConnection::STATUS_SYNCING])->save();

        if ($isPlaid) {
            SyncPlaidConnectionJob::dispatchAfterResponse($connection->id, 'manual');

            return true;
        }

        if ($connection->provider === BankConnection::PROVIDER_IBKR) {
            SyncIbkrConnectionJob::dispatchAfterResponse($connection->id, 'manual');

            return true;
        }

        SyncEnableBankingConnectionJob::dispatchAfterResponse(
            $connection->id,
            'manual',
            PsuHeaders::fromRequest($request, Arr::wrap(Arr::get($connection->metadata, 'required_psu_headers', []))),
        );

        return true;
    }

    private function plaidCooldownMinutes(): int
    {
        return max(1, (int) config('services.plaid.manual_sync_cooldown_minutes', 60));
    }
}
