<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankConnectionsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $connections = $request->user()
            ->bankConnections()
            ->where('status', '!=', 'failed')
            ->with(['accounts:id,bank_connection_id,name,iban,currency,raw_payload'])
            ->latest()
            ->get()
            ->map(fn ($connection) => [
                'id' => $connection->id,
                'aspsp_name' => $connection->aspsp_name,
                'aspsp_country' => $connection->aspsp_country,
                'status' => $connection->status,
                'authorized_at' => $connection->authorized_at?->toIso8601String(),
                'accounts' => $connection->accounts->map(fn ($account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'account_type' => data_get($account->raw_payload, 'cash_account_type')
                        ?? data_get($account->raw_payload, 'cashAccountType')
                        ?? data_get($account->raw_payload, 'account_type')
                        ?? data_get($account->raw_payload, 'accountType'),
                    'iban' => $account->iban,
                    'currency' => $account->currency,
                ])->values(),
            ])
            ->values();

        return Inertia::render('bank-connections', [
            'connections' => $connections,
        ]);
    }
}
