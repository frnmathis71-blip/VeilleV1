<?php

namespace App\Services\Analysis;

use Carbon\CarbonInterface;

/** Scoring rules shared by every analyzer, so the configured weights mean the same thing everywhere. */
class Scoring
{
    public static function freshness(?CarbonInterface $publishedAt): int
    {
        $age = $publishedAt ? max(0, $publishedAt->diffInDays(now(), false)) : null;

        return $age === null ? 30 : ($age <= 2 ? 100 : ($age <= 7 ? 70 : ($age <= 30 ? 40 : 10)));
    }

    /**
     * @param  array<string, int>  $criteria
     * @param  array<string, int|string>  $weights
     */
    public static function weighted(array $criteria, array $weights): int
    {
        $total = max(1, array_sum($weights));
        $score = 0;
        foreach ($criteria as $key => $value) {
            $score += $value * ($weights[$key] ?? 0) / $total;
        }

        return min(100, max(0, (int) round($score)));
    }

    /**
     * @param  array<int, string>|string  $words  comma separated string or list
     * @return list<string> the words found in the text, as whole words
     */
    public static function matches(string $text, array|string $words): array
    {
        $text = mb_strtolower($text);
        $words = is_string($words) ? explode(',', $words) : $words;

        return array_values(array_filter(array_map(fn (string $w): string => mb_strtolower(trim($w)), $words), static fn (string $word): bool => $word !== ''
            && preg_match('/(?<![\pL\pN])'.preg_quote($word, '/').'(?![\pL\pN])/u', $text) === 1));
    }
}
