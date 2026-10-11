<?php

namespace App\Services\Feeds;

use RuntimeException;
use SimplePie\Item;
use SimplePie\SimplePie;

class FeedParser
{
    /** @return list<FeedEntry> */
    public function parse(string $xml): array
    {
        if (strlen($xml) > 2_000_000 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new RuntimeException('Flux trop volumineux ou déclaration XML interdite.');
        }
        $feed = new SimplePie;
        $feed->enable_cache(false);
        $feed->set_autodiscovery_level(0);
        $feed->set_raw_data($xml);
        if (! $feed->init()) {
            throw new RuntimeException('Le document n’est pas un flux RSS/Atom valide.');
        }
        $entries = [];
        foreach ($feed->get_items(0, 200) as $item) {
            $url = self::safeLink((string) $item->get_permalink());
            if ($url === null) {
                continue;
            }
            $description = self::plain((string) ($item->get_description() ?: $item->get_content()));
            $image = self::image($item);
            $date = $item->get_date('c');
            $entries[] = new FeedEntry(
                mb_substr(self::plain((string) $item->get_title()), 0, 1000) ?: 'Article sans titre',
                $url, $item->get_id(true) ?: null, mb_substr($description, 0, 12000),
                $item->get_author()?->get_name(), $date ?: null, $image,
                $feed->get_language() ?: null,
            );
        }

        return $entries;
    }

    /** Illustration announced by the feed: media thumbnail, image enclosure, then first image of the content. */
    private static function image(Item $item): ?string
    {
        $candidates = [];
        foreach ((array) $item->get_enclosures() as $enclosure) {
            $candidates[] = (string) $enclosure->get_thumbnail();
            $link = (string) $enclosure->get_link();
            if ($enclosure->get_medium() === 'image' || str_starts_with((string) $enclosure->get_type(), 'image/')
                || preg_match('/\.(jpe?g|png|gif|webp|avif)(\?|$)/i', $link)) {
                $candidates[] = $link;
            }
        }
        $html = (string) $item->get_content().' '.(string) $item->get_description();
        if (preg_match_all('/<img\b[^>]*>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                // Tracking pixels are 1×1 images: they would make an empty thumbnail.
                if (preg_match('/\b(?:width|height)=["\']?1["\'\s>\/]/i', $tag)) {
                    continue;
                }
                if (preg_match('/\bsrc=["\']([^"\']+)["\']/i', $tag, $src)) {
                    $candidates[] = html_entity_decode($src[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        }
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && ($url = self::safeLink($candidate)) !== null) {
                return $url;
            }
        }

        return null;
    }

    public static function plain(string $html): string
    {
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? '';

        return self::withoutEmoji(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Removes emoji and pictograms so that titles and excerpts stay sober; collapses the spaces left behind. */
    public static function withoutEmoji(string $text): string
    {
        $text = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2300}-\x{23FF}\x{FE00}-\x{FE0F}\x{200D}\x{20E3}\x{E0020}-\x{E007F}]/u', '', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public static function safeLink(string $url): ?string
    {
        if (strlen($url) > 4000 || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            return null;
        }

        return $url;
    }

    public static function canonical(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }
        $query = [];
        foreach (explode('&', $parts['query'] ?? '') as $pair) {
            $key = strtolower(urldecode(explode('=', $pair, 2)[0]));
            if ($pair !== '' && ! str_starts_with($key, 'utm_') && ! in_array($key, ['fbclid', 'gclid'], true)) {
                $query[] = $pair;
            }
        }
        sort($query);

        return strtolower($parts['scheme'] ?? 'https').'://'.strtolower($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '/')
            .($query ? '?'.implode('&', $query) : '');
    }
}
