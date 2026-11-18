<?php

namespace App\Http\Controllers;

use App\Models\BankConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletSyncStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $counts = BankConnection::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('provider', [BankConnection::PROVIDER_ENABLE_BANKING, BankConnection::PROVIDER_IBKR])
            ->where('status', '!=', BankConnection::STATUS_PENDING)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $syncing = (int) ($counts[BankConnection::STATUS_SYNCING] ?? 0);

        return response()->json([
            'is_syncing' => $syncing > 0,
            'syncing_connections' => $syncing,
            'connected_connections' => (int) ($counts[BankConnection::STATUS_CONNECTED] ?? 0),
            'needs_reauth_connections' => (int) ($counts[BankConnection::STATUS_NEEDS_REAUTH] ?? 0),
            'failed_connections' => (int) ($counts[BankConnection::STATUS_SYNC_FAILED] ?? 0),
            'total_connections' => (int) $counts->sum(),
        ]);
    }
}
