<?php

namespace App\Services\Ibkr;

use RuntimeException;

/**
 * An error reported by IBKR's Flex Web Service.
 *
 * @see https://www.ibkrguides.com/orgportal/performanceandstatements/flex3error.htm
 */
class FlexException extends RuntimeException
{
    /** The report isn't ready (yet) or IBKR is busy: try again later. */
    private const RETRY_LATER = ['1001', '1004', '1005', '1006', '1007', '1008', '1009', '1018', '1019', '1021'];

    /** The token no longer works: the user has to generate a new one in IBKR. */
    private const NEW_TOKEN_NEEDED = ['1011', '1012', '1015'];

    public function __construct(string $message, public readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }

    public function shouldRetryLater(): bool
    {
        return in_array($this->errorCode, self::RETRY_LATER, true);
    }

    public function needsNewToken(): bool
    {
        return in_array($this->errorCode, self::NEW_TOKEN_NEEDED, true);
    }

    public function friendlyMessage(): string
    {
        return match ($this->errorCode) {
            '1012' => 'Your IBKR Flex token has expired. Generate a new one in IBKR and update it here.',
            '1015', '1011' => 'IBKR does not accept the Flex token. Generate a new one in IBKR and update it here.',
            '1013' => 'The Flex token is limited to another IP address. Remove the IP restriction in IBKR or generate a new token.',
            '1014' => 'IBKR does not know this Flex Query ID. Check the number in Flex Queries.',
            '1018' => 'Too many requests to IBKR. Trying again shortly.',
            '1003' => 'IBKR has no report for this query.',
            '1010' => 'This is a legacy Flex Query. Create an Activity Flex Query instead.',
            default => $this->shouldRetryLater()
                ? 'IBKR is still preparing the report. Trying again shortly.'
                : 'IBKR returned an error: '.$this->getMessage(),
        };
    }
}
