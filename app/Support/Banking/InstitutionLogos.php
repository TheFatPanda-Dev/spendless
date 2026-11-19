<?php

namespace App\Support\Banking;

use App\Models\BankConnection;
use DOMDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Logos for wallets whose provider sends none (imports, IBKR, ISA, pension). A known
 * institution's icon is fetched once by the server and kept in public storage, so the
 * browser only ever loads it from this site.
 */
class InstitutionLogos
{
    public const DIRECTORY = 'institution-logos';

    /**
     * Name fragment (matched as whole words, in order) => the institution's website.
     *
     * @var array<string, string>
     */
    public const KNOWN = [
        'barclaycard' => 'barclaycard.co.uk',
        'barclays' => 'barclays.co.uk',
        'nationwide' => 'nationwide.co.uk',
        'santander' => 'santander.co.uk',
        'revolut' => 'revolut.com',
        'monzo' => 'monzo.com',
        'starling' => 'starlingbank.com',
        'hsbc' => 'hsbc.co.uk',
        'lloyds' => 'lloydsbank.com',
        'natwest' => 'natwest.com',
        'halifax' => 'halifax.co.uk',
        'first direct' => 'firstdirect.com',
        'chase' => 'chase.co.uk',
        'american express' => 'americanexpress.com',
        'amex' => 'americanexpress.com',
        'wise' => 'wise.com',
        'n26' => 'n26.com',
        'paypal' => 'paypal.com',
        'al rayan' => 'alrayanbank.co.uk',
        'pensionbee' => 'pensionbee.com',
        'interactive brokers' => 'interactivebrokers.com',
        'ibkr' => 'interactivebrokers.com',
        'trading 212' => 'trading212.com',
        'trading212' => 'trading212.com',
        'vanguard' => 'vanguardinvestor.co.uk',
        'nlb' => 'nlb.si',
        'delavska' => 'dh.si',
        'otp' => 'otpbanka.si',
        'skb' => 'otpbanka.si',
        'nkbm' => 'otpbanka.si',
        'intesa' => 'intesasanpaolobank.si',
        'unicredit' => 'unicreditbank.si',
        'sparkasse' => 'sparkasse.si',
        'addiko' => 'addiko.si',
        'gorenjska banka' => 'gbkr.si',
    ];

    private const MAX_BYTES = 512_000;

    private const RETRY_AFTER_DAYS = 7;

    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    /** @var array<string, string>|null domain => stored path */
    private static ?array $stored = null;

    public static function domainFor(?string $institution, ?string $provider = null): ?string
    {
        if ($provider === BankConnection::PROVIDER_IBKR) {
            return 'interactivebrokers.com';
        }

        $name = Str::lower(trim((string) $institution));

        if ($name === '') {
            return null;
        }

        foreach (self::KNOWN as $fragment => $domain) {
            if (preg_match('/\b'.preg_quote($fragment, '/').'\b/u', $name) === 1) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * The stored logo's address for this institution, or null when there is none yet.
     */
    public static function url(?string $institution, ?string $provider = null): ?string
    {
        $domain = self::domainFor($institution, $provider);
        $path = $domain !== null ? (self::stored()[$domain] ?? null) : null;

        return $path !== null ? '/storage/'.$path : null;
    }

    public static function has(string $domain): bool
    {
        return isset(self::stored()[$domain]);
    }

    public static function recentlyMissed(string $domain): bool
    {
        $disk = Storage::disk('public');
        $marker = self::DIRECTORY.'/'.$domain.'.miss';

        return $disk->exists($marker)
            && $disk->lastModified($marker) > now()->subDays(self::RETRY_AFTER_DAYS)->getTimestamp();
    }

    /**
     * Fetch and store the icon for a domain from our fixed list: the site's own
     * apple-touch or icon links first, then DuckDuckGo's icon service. SVG is
     * refused, since it would be served from this site's own origin.
     */
    public static function fetch(string $domain): bool
    {
        if (! in_array($domain, self::KNOWN, true) && $domain !== 'interactivebrokers.com') {
            return false;
        }

        $disk = Storage::disk('public');

        foreach (self::candidates($domain) as $url) {
            try {
                $response = self::client()->get($url);
            } catch (Throwable) {
                continue;
            }

            $type = Str::lower(Str::before((string) $response->header('Content-Type'), ';'));
            $body = $response->body();
            $extension = match ($type) {
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
                default => null,
            };

            if (! $response->successful() || $extension === null || strlen($body) < 100 || strlen($body) > self::MAX_BYTES) {
                continue;
            }

            foreach ($disk->files(self::DIRECTORY) as $old) {
                if (Str::startsWith(basename($old), $domain.'.')) {
                    $disk->delete($old);
                }
            }

            $disk->put(self::DIRECTORY.'/'.$domain.'.'.$extension, $body);
            self::$stored = null;

            return true;
        }

        $disk->put(self::DIRECTORY.'/'.$domain.'.miss', now()->toIso8601String());

        return false;
    }

    public static function forget(): void
    {
        self::$stored = null;
    }

    /**
     * @return list<string>
     */
    private static function candidates(string $domain): array
    {
        $fromPage = [];

        foreach (["https://www.{$domain}/", "https://{$domain}/"] as $home) {
            try {
                $response = self::client()->accept('text/html')->get($home);
            } catch (Throwable) {
                continue;
            }

            if ($response->successful()) {
                $fromPage = self::iconLinks($response->body(), (string) ($response->effectiveUri() ?? $home));
                break;
            }
        }

        return array_values(array_unique([
            ...$fromPage,
            "https://www.{$domain}/apple-touch-icon.png",
            "https://{$domain}/apple-touch-icon.png",
            "https://icons.duckduckgo.com/ip3/{$domain}.ico",
        ]));
    }

    /**
     * Icon links from a page, biggest and apple-touch first.
     *
     * @return list<string>
     */
    private static function iconLinks(string $html, string $base): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $links = [];

        foreach ($document->getElementsByTagName('link') as $link) {
            $rel = Str::lower($link->getAttribute('rel'));
            $href = trim($link->getAttribute('href'));

            if ($href === '' || ! Str::contains($rel, 'icon') || Str::endsWith(Str::lower(Str::before($href, '?')), '.svg')) {
                continue;
            }

            $url = self::absolute($href, $base);

            if ($url === null) {
                continue;
            }

            preg_match('/(\d+)x\d+/', $link->getAttribute('sizes'), $size);
            $links[] = [
                'url' => $url,
                'score' => (Str::contains($rel, 'apple-touch') ? 10_000 : 0) + (int) ($size[1] ?? 32),
            ];
        }

        usort($links, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_column($links, 'url');
    }

    private static function absolute(string $href, string $base): ?string
    {
        if (Str::startsWith($href, '//')) {
            $href = 'https:'.$href;
        }

        if (preg_match('#^https://#i', $href) === 1) {
            return $href;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) === 1) {
            return null; // data:, http:, javascript: and the like
        }

        $parts = parse_url($base);

        if (! isset($parts['host'])) {
            return null;
        }

        $origin = 'https://'.$parts['host'];

        if (Str::startsWith($href, '/')) {
            return $origin.$href;
        }

        $directory = rtrim(Str::beforeLast($parts['path'] ?? '/', '/'), '/');

        return $origin.$directory.'/'.$href;
    }

    private static function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(8)
            ->connectTimeout(5)
            ->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['https']]]);
    }

    /**
     * @return array<string, string>
     */
    private static function stored(): array
    {
        if (self::$stored !== null) {
            return self::$stored;
        }

        $stored = [];

        try {
            foreach (Storage::disk('public')->files(self::DIRECTORY) as $path) {
                $file = basename($path);

                if (! Str::endsWith($file, '.miss')) {
                    $stored[Str::beforeLast($file, '.')] = $path;
                }
            }
        } catch (Throwable) {
            // No logos stored yet.
        }

        return self::$stored = $stored;
    }
}
