<?php

namespace App\Services\EnableBanking;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin wrapper around the Enable Banking REST API.
 *
 * @see https://enablebanking.com/docs/api/reference/
 */
class EnableBankingClient
{
    private const ASPSP_CACHE_SECONDS = 86400;

    public function __construct(private readonly EnableBankingJwtFactory $jwtFactory) {}

    public function isConfigured(): bool
    {
        return (string) config('services.enable_banking.app_id') !== ''
            && (string) config('services.enable_banking.private_key_path') !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getApplication(): array
    {
        return $this->send('get', '/application');
    }

    /**
     * Banks that offer account information in a country. Cached for a day because the
     * list rarely changes and the bank picker asks for it every time it opens.
     *
     * @return list<array<string, mixed>>
     */
    public function getAspsps(string $country): array
    {
        $country = strtoupper($country);

        return Cache::remember(
            'enable-banking:aspsps:'.$country,
            self::ASPSP_CACHE_SECONDS,
            fn (): array => array_values($this->send('get', '/aspsps', [
                'country' => $country,
                'service' => 'AIS',
            ])['aspsps'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAspsp(string $country, string $name): ?array
    {
        $normalizedName = mb_strtolower(trim($name));

        foreach ($this->getAspsps($country) as $aspsp) {
            if (mb_strtolower(trim((string) ($aspsp['name'] ?? ''))) === $normalizedName) {
                return $aspsp;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $psuHeaders
     * @return array<string, mixed>
     */
    public function startAuthorization(array $payload, array $psuHeaders = []): array
    {
        return $this->send('post', '/auth', $payload, $psuHeaders);
    }

    /**
     * @param  array<string, string>  $psuHeaders
     * @return array<string, mixed>
     */
    public function authorizeSession(string $code, array $psuHeaders = []): array
    {
        return $this->send('post', '/sessions', ['code' => $code], $psuHeaders);
    }

    /**
     * @param  array<string, string>  $psuHeaders
     */
    public function deleteSession(string $sessionId, array $psuHeaders = []): void
    {
        $this->send('delete', '/sessions/'.rawurlencode($sessionId), [], $psuHeaders);
    }

    /**
     * @param  array<string, string>  $psuHeaders
     * @return list<array<string, mixed>>
     */
    public function getBalances(string $accountUid, array $psuHeaders = []): array
    {
        return array_values($this->send('get', '/accounts/'.rawurlencode($accountUid).'/balances', [], $psuHeaders)['balances'] ?? []);
    }

    /**
     * @param  array<string, string>  $query
     * @param  array<string, string>  $psuHeaders
     * @return array{transactions?: list<array<string, mixed>>, continuation_key?: string|null}
     */
    public function getTransactions(string $accountUid, array $query, array $psuHeaders = []): array
    {
        return $this->send('get', '/accounts/'.rawurlencode($accountUid).'/transactions', $query, $psuHeaders);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $psuHeaders
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $data = [], array $psuHeaders = []): array
    {
        try {
            $request = $this->request()->withHeaders($psuHeaders);

            $response = match ($method) {
                'get' => $request->get($path, $data),
                'post' => $request->post($path, $data),
                'delete' => $request->delete($path),
            };
        } catch (ConnectionException $exception) {
            throw new EnableBankingException('Could not reach Enable Banking.', previous: $exception);
        } catch (Throwable $exception) {
            throw new EnableBankingException($exception->getMessage(), previous: $exception);
        }

        if ($response->failed()) {
            throw EnableBankingException::fromResponse($response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.enable_banking.base_url'))
            ->withToken($this->jwtFactory->makeToken())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(40)
            ->retry(
                2,
                500,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
                throw: false,
            );
    }
}
