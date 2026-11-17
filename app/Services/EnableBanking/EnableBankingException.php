<?php

namespace App\Services\EnableBanking;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class EnableBankingException extends RuntimeException
{
    /**
     * Error codes meaning the bank consent is gone and the user has to authorise again.
     */
    private const REAUTH_ERRORS = [
        'EXPIRED_SESSION',
        'REVOKED_SESSION',
        'CLOSED_SESSION',
        'SESSION_DOES_NOT_EXIST',
        'WRONG_SESSION_STATUS',
        'ASPSP_PSU_ACTION_REQUIRED',
    ];

    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $errorCode = $response->json('error');
        $message = (string) ($response->json('message') ?: 'Enable Banking request failed.');
        $detail = $response->json('detail');

        if (is_string($detail) && $detail !== '') {
            $message .= ' ('.$detail.')';
        }

        return new self(
            message: $message,
            errorCode: is_string($errorCode) && $errorCode !== '' ? $errorCode : null,
            status: $response->status(),
        );
    }

    public function requiresReauthorization(): bool
    {
        return in_array($this->errorCode, self::REAUTH_ERRORS, true);
    }

    public function isRateLimited(): bool
    {
        return $this->errorCode === 'ASPSP_RATE_LIMIT_EXCEEDED' || $this->status === 429;
    }
}
