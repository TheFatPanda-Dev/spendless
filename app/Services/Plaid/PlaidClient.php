<?php

namespace App\Services\Plaid;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PlaidClient
{
    public function createLinkToken(array $payload): array
    {
        return $this->request()
            ->post('/link/token/create', $payload)
            ->throw()
            ->json();
    }

    public function exchangePublicToken(string $publicToken): array
    {
        return $this->request()
            ->post('/item/public_token/exchange', [
                'public_token' => $publicToken,
            ])
            ->throw()
            ->json();
    }

    public function getItem(string $accessToken): array
    {
        return $this->request()
            ->post('/item/get', [
                'access_token' => $accessToken,
            ])
            ->throw()
            ->json();
    }

    public function getInstitution(string $institutionId, array $countryCodes): array
    {
        return $this->request()
            ->post('/institutions/get_by_id', [
                'institution_id' => $institutionId,
                'country_codes' => $countryCodes,
                'options' => [
                    'include_optional_metadata' => true,
                ],
            ])
            ->throw()
            ->json();
    }

    public function getAccounts(string $accessToken): array
    {
        return $this->request()
            ->post('/accounts/get', [
                'access_token' => $accessToken,
            ])
            ->throw()
            ->json();
    }

    public function refreshTransactions(string $accessToken): array
    {
        return $this->request()
            ->post('/transactions/refresh', [
                'access_token' => $accessToken,
            ])
            ->throw()
            ->json();
    }

    public function syncTransactions(string $accessToken, ?string $cursor, int $count = 100): array
    {
        $payload = [
            'access_token' => $accessToken,
            'count' => $count,
        ];

        if ($cursor !== null && $cursor !== '') {
            $payload['cursor'] = $cursor;
        }

        return $this->request()
            ->post('/transactions/sync', $payload)
            ->throw()
            ->json();
    }

    private function request(): PendingRequest
    {
        $clientId = (string) config('services.plaid.client_id');
        $secret = (string) config('services.plaid.secret');
        $version = (string) config('services.plaid.version', '2020-09-14');

        if ($clientId === '' || $secret === '') {
            throw new RuntimeException('Plaid client credentials are not configured.');
        }

        return Http::baseUrl((string) config('services.plaid.base_url', 'https://sandbox.plaid.com'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'PLAID-CLIENT-ID' => $clientId,
                'PLAID-SECRET' => $secret,
                'Plaid-Version' => $version,
            ])
            ->retry([200, 500, 1000]);
    }
}
