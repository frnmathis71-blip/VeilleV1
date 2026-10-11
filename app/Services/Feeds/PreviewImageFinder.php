<?php

namespace App\Services\Feeds;

/**
 * Finds the preview image an article page declares for social networks (og:image, twitter:image).
 * Only the URL is kept: the image itself is displayed by the visitor's browser.
 */
class PreviewImageFinder
{
    private const PROPERTIES = ['og:image:secure_url', 'og:image', 'og:image:url', 'twitter:image', 'twitter:image:src'];

    public function __construct(private FeedFetcher $fetcher) {}

    public function find(string $pageUrl): ?string
    {
        return self::extract($this->fetcher->fetchPageHead($pageUrl), $pageUrl);
    }

    public static function extract(string $html, string $pageUrl): ?string
    {
        $found = [];
        if (preg_match_all('/<meta\b[^>]*>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                $key = strtolower(self::attribute($tag, 'property') ?? self::attribute($tag, 'name') ?? '');
                $content = self::attribute($tag, 'content');
                if (in_array($key, self::PROPERTIES, true) && $content !== null && $content !== '' && ! isset($found[$key])) {
                    $found[$key] = $content;
                }
            }
        }
        foreach (self::PROPERTIES as $property) {
            if (isset($found[$property])) {
                $url = FeedParser::safeLink(FeedFetcher::resolve($pageUrl, $found[$property]));
                if ($url !== null) {
                    return $url;
                }
            }
        }

        return null;
    }

    private static function attribute(string $tag, string $name): ?string
    {
        if (preg_match('/\b'.$name.'\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $m)) {
            return html_entity_decode(($m[1] ?? '') !== '' ? $m[1] : (($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }
}
