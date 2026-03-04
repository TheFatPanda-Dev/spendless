<?php

namespace App\Services\EnableBanking;

use RuntimeException;

class EnableBankingJwtFactory
{
    public function makeToken(int $ttlSeconds = 3600): string
    {
        $appId = (string) config('services.enable_banking.app_id');
        $configuredPrivateKeyPath = (string) config('services.enable_banking.private_key_path');

        if ($appId === '' || $configuredPrivateKeyPath === '') {
            throw new RuntimeException('Enable Banking credentials are not configured.');
        }

        $privateKeyPath = str_starts_with($configuredPrivateKeyPath, '/')
            ? $configuredPrivateKeyPath
            : base_path($configuredPrivateKeyPath);

        $keyContent = @file_get_contents($privateKeyPath);

        if ($keyContent === false) {
            throw new RuntimeException('Enable Banking private key file could not be read.');
        }

        $privateKey = openssl_pkey_get_private($keyContent);

        if ($privateKey === false) {
            throw new RuntimeException('Enable Banking private key is invalid.');
        }

        $now = time();
        $header = [
            'typ' => 'JWT',
            'alg' => 'RS256',
            'kid' => $appId,
        ];

        $payload = [
            'iss' => (string) config('services.enable_banking.jwt_issuer', 'enablebanking.com'),
            'aud' => (string) config('services.enable_banking.jwt_audience', 'api.enablebanking.com'),
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signingInput = $encodedHeader.'.'.$encodedPayload;

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Enable Banking JWT signature could not be created.');
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
