<?php

namespace App\Services\Plaid;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The few Plaid endpoints SpendLess uses: Link tokens, token exchange, /transactions/sync
 * (which also returns balances, so no separate balance calls) and /item/remove.
 */
class PlaidClient
{
    private const API_VERSION = '2020-09-14';

    public function isConfigured(): bool
    {
        return filled(config('services.plaid.client_id')) && filled(config('services.plaid.secret'));
    }

    public function environment(): string
    {
        return config('services.plaid.environment') === 'sandbox' ? 'sandbox' : 'production';
    }

    /**
     * A Link token for connecting a bank, or for update mode when $accessToken is given.
     *
     * @return array{link_token: string, expiration: string|null}
     */
    public function createLinkToken(User $user, ?string $accessToken = null): array
    {
        $body = [
            'client_name' => (string) config('app.name', 'SpendLess'),
            'language' => 'en',
            'country_codes' => (array) config('services.plaid.country_codes', ['GB']),
            'user' => ['client_user_id' => (string) $user->id],
            'redirect_uri' => (string) config('services.plaid.redirect_uri'),
        ];

        if ($accessToken !== null) {
            $body['access_token'] = $accessToken;
        } else {
            $body['products'] = ['transactions'];
            $body['transactions'] = ['days_requested' => max(30, min(730, (int) config('services.plaid.history_days', 90)))];
        }

        $response = $this->post('/link/token/create', $body);

        return [
            'link_token' => (string) $response['link_token'],
            'expiration' => isset($response['expiration']) ? (string) $response['expiration'] : null,
        ];
    }

    /**
     * @return array{access_token: string, item_id: string}
     */
    public function exchangePublicToken(string $publicToken): array
    {
        $response = $this->post('/item/public_token/exchange', ['public_token' => $publicToken]);

        return [
            'access_token' => (string) $response['access_token'],
            'item_id' => (string) $response['item_id'],
        ];
    }

    /**
     * One page of transaction changes since $cursor (null = from the beginning). Counted
     * towards the free allowance.
     *
     * @return array<string, mixed>
     */
    public function transactionsSync(string $accessToken, ?string $cursor): array
    {
        $body = [
            'access_token' => $accessToken,
            'count' => 500,
            'options' => ['include_original_description' => true],
        ];

        if ($cursor !== null && $cursor !== '') {
            $body['cursor'] = $cursor;
        }

        PlaidUsage::record();

        return $this->post('/transactions/sync', $body);
    }

    public function removeItem(string $accessToken): void
    {
        $this->post('/item/remove', ['access_token' => $accessToken]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->asJson()
                ->withHeaders(['Plaid-Version' => self::API_VERSION])
                ->timeout(60)
                ->post($path, [
                    'client_id' => (string) config('services.plaid.client_id'),
                    'secret' => (string) config('services.plaid.secret'),
                    ...$body,
                ]);
        } catch (ConnectionException $exception) {
            throw new PlaidException('Could not reach Plaid: '.$exception->getMessage(), previous: $exception);
        }

        $json = $response->json();

        if ($response->failed() || ! is_array($json)) {
            throw PlaidException::fromResponse(is_array($json) ? $json : [], $response->status());
        }

        return $json;
    }

    private function baseUrl(): string
    {
        return $this->environment() === 'sandbox' ? 'https://sandbox.plaid.com' : 'https://production.plaid.com';
    }
}
