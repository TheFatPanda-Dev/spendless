<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateLinkTokenRequest;
use App\Http\Requests\ExchangePublicTokenRequest;
use App\Jobs\RefreshAllPlaidConnectionsJob;
use App\Jobs\SyncPlaidConnectionJob;
use App\Models\BankConnection;
use App\Models\Wallet;
use App\Services\Banking\BankActivityLogger;
use App\Services\Plaid\PlaidTransactionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class WalletBankConnectionController extends Controller
{
    public function createLinkToken(
        CreateLinkTokenRequest $request,
        Wallet $wallet,
        PlaidTransactionsService $transactionsService,
    ): JsonResponse {
        Gate::authorize('view', $wallet);

        $linkToken = $transactionsService->createLinkToken(
            $request->user(),
            $wallet,
            $request->validated('redirect_uri'),
        );

        return response()->json([
            'link_token' => $linkToken,
        ]);
    }

    public function exchangePublicToken(
        ExchangePublicTokenRequest $request,
        Wallet $wallet,
        PlaidTransactionsService $transactionsService,
        BankActivityLogger $activityLogger,
    ): JsonResponse {
        Gate::authorize('view', $wallet);

        $connection = $transactionsService->exchangePublicToken(
            $request->validated('public_token'),
            $wallet,
            $request->user(),
        );

        $activityLogger->log($request->user(), 'account_added', [
            'connection_id' => $connection->id,
            'wallet_id' => $wallet->id,
            'accounts_count' => $connection->accounts()->count(),
            'institution_name' => $connection->institution_name,
        ]);

        return response()->json([
            'connection_id' => $connection->id,
            'status' => $connection->status,
        ], Response::HTTP_CREATED);
    }

    public function refreshAll(Request $request, BankActivityLogger $activityLogger): JsonResponse
    {
        $request->user()
            ->bankConnections()
            ->where('provider', 'plaid')
            ->whereIn('status', ['connected', 'sync_failed'])
            ->update(['status' => 'syncing']);

        $activityLogger->log($request->user(), 'sync_all_requested');

        RefreshAllPlaidConnectionsJob::dispatch($request->user()->id);

        return response()->json([
            'queued' => true,
            'message' => 'Refresh for all linked Plaid connections has been queued.',
        ]);
    }

    public function refreshConnection(Request $request, BankConnection $bankConnection): JsonResponse
    {
        Gate::authorize('update', $bankConnection);

        abort_unless($bankConnection->provider === 'plaid', Response::HTTP_UNPROCESSABLE_ENTITY);

        $bankConnection->update([
            'status' => 'syncing',
        ]);

        SyncPlaidConnectionJob::dispatch($bankConnection->id, 'manual_single', true);

        return response()->json([
            'queued' => true,
            'message' => 'Refresh for this connection has been queued.',
        ]);
    }
}
