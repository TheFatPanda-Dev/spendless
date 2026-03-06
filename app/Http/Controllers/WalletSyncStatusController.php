<?php

namespace App\Http\Controllers;

use App\Models\BankConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletSyncStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $baseQuery = BankConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'plaid');

        $syncingCount = (clone $baseQuery)
            ->where('status', 'syncing')
            ->count();

        return response()->json([
            'is_syncing' => $syncingCount > 0,
            'syncing_connections' => $syncingCount,
            'connected_connections' => (clone $baseQuery)
                ->where('status', 'connected')
                ->count(),
            'total_connections' => (clone $baseQuery)->count(),
        ]);
    }
}
