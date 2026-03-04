<?php

namespace App\Services\EnableBanking;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class EnableBankingClient
{
    public function __construct(private readonly EnableBankingJwtFactory $jwtFactory) {}

    public function getAspsps(string $country): array
    {
        return $this->request()
            ->get('/aspsps', ['country' => strtoupper($country)])
            ->throw()
            ->json('aspsps', []);
    }

    public function startAuthorization(array $payload): array
    {
        return $this->request()
            ->post('/auth', $payload)
            ->throw()
            ->json();
    }

    public function authorizeSession(string $code): array
    {
        return $this->request()
            ->post('/sessions', ['code' => $code])
            ->throw()
            ->json();
    }

    public function getTransactions(string $accountUid, array $query): array
    {
        return $this->request()
            ->get('/accounts/'.$accountUid.'/transactions', $query)
            ->throw()
            ->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.enable_banking.base_url'))
            ->withToken($this->jwtFactory->makeToken())
            ->acceptJson()
            ->asJson()
            ->retry([200, 500, 1000]);
    }
}
