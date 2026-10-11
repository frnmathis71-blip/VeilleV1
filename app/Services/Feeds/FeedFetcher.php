<?php

namespace App\Services\Feeds;

use RuntimeException;

class FeedFetcher
{
    private const MAX_REDIRECTS = 3;

    private const BROWSER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 VeilleSIO/1.0';

    public function __construct(private PublicUrl $urls) {}

    public function fetch(string $url): string
    {
        if ($url === '') {
            throw new RuntimeException('URL du flux obligatoire.');
        }
        // Redirects are refused for feeds: administrators can enter the final feed URL.
        $response = $this->request($url, 'application/rss+xml, application/atom+xml, application/xml, text/xml', 2_000_000, false, 'VeilleSIO/1.0 RSS reader');
        if ($response['truncated']) {
            throw new RuntimeException('Le flux dépasse la limite de 2 Mo.');
        }
        if (! $response['ok'] || $response['code'] !== 200) {
            throw new RuntimeException('Flux indisponible (HTTP '.$response['code'].'). Les redirections sont refusées : utilisez l’URL finale.');
        }

        return $response['body'];
    }

    /**
     * Beginning of an article page, up to the end of its <head>: enough to read the preview image metadata.
     * The rest of the page is never downloaded. Each redirect is validated again against the SSRF rules.
     */
    public function fetchPageHead(string $url): string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            // Many publishers refuse unknown robots: identify as a regular browser for this metadata read.
            // WordPress sites often inline large styles before the meta tags, hence the 1 MB limit.
            $response = $this->request($url, 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8', 1_000_000, true, self::BROWSER_AGENT);
            if ($response['code'] >= 300 && $response['code'] < 400 && $response['location'] !== null) {
                $url = self::resolve($url, $response['location']);

                continue;
            }
            if ($response['code'] !== 200 || (! $response['ok'] && ! $response['truncated'])) {
                throw new RuntimeException('Page indisponible (HTTP '.$response['code'].').');
            }

            return $response['body'];
        }
        throw new RuntimeException('Trop de redirections.');
    }

    /** Absolute URL of a link found on $base (redirect target, relative image path…). */
    public static function resolve(string $base, string $link): string
    {
        $link = trim($link);
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $link)) {
            return $link; // Already absolute; other schemes (javascript:, data:…) are rejected by the caller.
        }
        $parts = parse_url($base) ?: [];
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($link, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$link;
        }
        if (str_starts_with($link, '/')) {
            return $origin.$link;
        }
        $directory = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/') ?: '/';

        return $origin.$directory.$link;
    }

    /**
     * @param  non-empty-string  $userAgent
     * @return array{ok: bool, code: int, body: string, truncated: bool, location: string|null}
     */
    private function request(string $url, string $accept, int $maxBytes, bool $stopAfterHead, string $userAgent): array
    {
        if ($url === '') {
            throw new RuntimeException('URL obligatoire.');
        }
        // A new connection pinned to a checked IP prevents DNS rebinding.
        $host = $this->urls->validate($url);
        $ips = $this->urls->resolve($host);
        $port = parse_url($url, PHP_URL_PORT) ?: (str_starts_with($url, 'https:') ? 443 : 80);
        $curl = curl_init();
        $body = '';
        $truncated = false;
        $location = null;
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RESOLVE => [$host.':'.$port.':'.$ips[0]],
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_PROXY => '',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => ['Accept: '.$accept, 'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8'],
            CURLOPT_HEADERFUNCTION => function ($handle, string $header) use (&$location): int {
                if (preg_match('/^location:\s*(\S+)/i', $header, $m)) {
                    $location = $m[1];
                }

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$body, &$truncated, $maxBytes, $stopAfterHead): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $truncated = true;

                    return 0;
                }
                $body .= $chunk;
                if ($stopAfterHead && stripos($body, '</head>') !== false) {
                    $truncated = true; // Everything needed has been received: stop the transfer.

                    return 0;
                }

                return strlen($chunk);
            },
        ]);
        $success = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return ['ok' => $success !== false, 'code' => $code, 'body' => $body, 'truncated' => $truncated, 'location' => $location];
    }
}
