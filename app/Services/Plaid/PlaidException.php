<?php

namespace App\Services\Plaid;

use RuntimeException;
use Throwable;

/**
 * A Plaid API error (https://plaid.com/docs/errors/), with its error_type and error_code.
 */
class PlaidException extends RuntimeException
{
    /** The user has to log in to their bank again (Link update mode). */
    private const REAUTH_CODES = [
        'ITEM_LOGIN_REQUIRED',
        'INVALID_CREDENTIALS',
        'INSUFFICIENT_CREDENTIALS',
        'ITEM_LOCKED',
        'USER_SETUP_REQUIRED',
        'ACCESS_NOT_GRANTED',
        'ADDITIONAL_CONSENT_REQUIRED',
        'ITEM_NOT_FOUND',
        'INVALID_ACCESS_TOKEN',
        'USER_PERMISSION_REVOKED',
        'PENDING_EXPIRATION',
    ];

    public function __construct(
        string $message,
        public readonly ?string $errorType = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $displayMessage = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromResponse(array $body, int $status): self
    {
        $code = isset($body['error_code']) ? (string) $body['error_code'] : null;

        return new self(
            sprintf('Plaid %s: %s', $code ?? 'HTTP '.$status, (string) ($body['error_message'] ?? 'request failed')),
            isset($body['error_type']) ? (string) $body['error_type'] : null,
            $code,
            isset($body['display_message']) && is_string($body['display_message']) ? $body['display_message'] : null,
        );
    }

    public function requiresReauthorization(): bool
    {
        return in_array($this->errorCode, self::REAUTH_CODES, true);
    }

    public function isRateLimited(): bool
    {
        return $this->errorType === 'RATE_LIMIT_EXCEEDED';
    }

    /** Data changed while paging through /transactions/sync: restart from the first cursor. */
    public function isMutationDuringPagination(): bool
    {
        return $this->errorCode === 'TRANSACTIONS_SYNC_MUTATION_DURING_PAGINATION';
    }

    public function friendlyMessage(): string
    {
        return match (true) {
            $this->requiresReauthorization() => 'The bank needs you to log in again. Reconnect it to keep syncing.',
            $this->isRateLimited() => 'Plaid asked us to slow down. Trying again at the next sync.',
            default => $this->displayMessage ?: 'Sync failed: '.$this->getMessage(),
        };
    }
}
