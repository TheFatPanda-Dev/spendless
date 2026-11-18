<?php

namespace App\Services\Ibkr;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use SimpleXMLElement;

/**
 * IBKR Flex Web Service (version 3): ask for a report, then fetch it once generated.
 * Limits: 1 request per second, 10 per minute per token.
 */
class FlexClient
{
    public const BASE_URL = 'https://ndcdyn.interactivebrokers.com/AccountManagement/FlexWebService';

    private const POLL_ATTEMPTS = 7;

    private const POLL_SECONDS = 7;

    /**
     * Returns the FlexQueryResponse XML.
     */
    public function download(string $token, string $queryId): string
    {
        $request = $this->get(self::BASE_URL.'/SendRequest', ['t' => $token, 'q' => $queryId, 'v' => '3']);
        $response = $this->xml($request);

        if (strcasecmp((string) $response->Status, 'Success') !== 0) {
            throw $this->error($response);
        }

        $reference = trim((string) $response->ReferenceCode);
        $statementUrl = trim((string) ($response->Url ?? '')) ?: trim((string) ($response->url ?? '')) ?: self::BASE_URL.'/GetStatement';

        if ($reference === '') {
            throw new FlexException('IBKR did not return a reference code.');
        }

        $lastError = null;

        for ($attempt = 1; $attempt <= self::POLL_ATTEMPTS; $attempt++) {
            Sleep::for($attempt === 1 ? 3 : self::POLL_SECONDS)->seconds();

            $body = $this->get($statementUrl, ['q' => $reference, 't' => $token, 'v' => '3']);

            if (str_contains($body, '<FlexQueryResponse')) {
                return $body;
            }

            $lastError = $this->error($this->xml($body));

            if (! $lastError->shouldRetryLater()) {
                throw $lastError;
            }
        }

        throw $lastError ?? new FlexException('IBKR did not finish the report in time.', '1019');
    }

    /**
     * @param  array<string, string>  $query
     */
    private function get(string $url, array $query): string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'SpendLess/1.0'])
                ->connectTimeout(10)
                ->timeout(60)
                ->get($url, $query);
        } catch (ConnectionException $exception) {
            throw new FlexException('Could not reach IBKR: '.$exception->getMessage(), '1001');
        }

        if ($response->serverError()) {
            throw new FlexException('IBKR is unavailable (HTTP '.$response->status().').', '1009');
        }

        return $response->body();
    }

    private function xml(string $body): SimpleXMLElement
    {
        $xml = @simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET);

        if ($xml === false) {
            throw new FlexException('IBKR sent an unreadable response.', '1001');
        }

        return $xml;
    }

    private function error(SimpleXMLElement $response): FlexException
    {
        return new FlexException(
            trim((string) $response->ErrorMessage) ?: 'Unknown error',
            trim((string) $response->ErrorCode) ?: null,
        );
    }
}
