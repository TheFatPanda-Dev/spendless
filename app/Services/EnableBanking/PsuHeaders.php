<?php

namespace App\Services\EnableBanking;

use Illuminate\Http\Request;

/**
 * "PSU" headers tell the bank the account holder is present right now. Requests that
 * carry them are not counted against the PSD2 limit of ~4 unattended refreshes a day.
 *
 * Enable Banking rejects a request that sends only some of a bank's required PSU
 * headers, so when a required header can't be derived we send none at all.
 */
final class PsuHeaders
{
    /**
     * @param  list<string>  $requiredHeaders  The bank's `required_psu_headers`.
     * @return array<string, string>
     */
    public static function fromRequest(Request $request, array $requiredHeaders = []): array
    {
        $headers = array_filter([
            'Psu-Ip-Address' => (string) $request->ip(),
            'Psu-User-Agent' => (string) $request->userAgent(),
            'Psu-Referer' => (string) $request->headers->get('referer', ''),
            'Psu-Accept' => (string) $request->headers->get('accept', ''),
            'Psu-Accept-Charset' => (string) $request->headers->get('accept-charset', ''),
            'Psu-Accept-Encoding' => (string) $request->headers->get('accept-encoding', ''),
            'Psu-Accept-language' => (string) $request->headers->get('accept-language', ''),
        ], fn (string $value): bool => $value !== '');

        $available = array_map('strtolower', array_keys($headers));

        foreach ($requiredHeaders as $requiredHeader) {
            if (! in_array(strtolower((string) $requiredHeader), $available, true)) {
                return [];
            }
        }

        return $headers;
    }
}
