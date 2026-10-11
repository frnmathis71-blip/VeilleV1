<?php

namespace App\Services\Analysis;

use App\Contracts\ArticleAnalyzer;
use App\Enums\AnalysisStatus;
use App\Enums\RelevanceVerdict;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalAnalyzer implements ArticleAnalyzer
{
    public const VERSION = 'local-1.0';

    /** @param array<string, mixed> $settings */
    public function analyze(Article $article, array $settings): void
    {
        if ($article->analysis_status === AnalysisStatus::Completed && $article->analysis()->exists()) {
            return;
        }
        $text = mb_strtolower($article->title.' '.$article->description);
        $categories = [];
        foreach (Category::where('is_monitored', true)->get() as $category) {
            $words = $category->keywords ?: config('watch.keywords.'.$category->slug, []);
            if ($this->hits($text, $words) > 0) {
                $categories[] = $category->id;
            }
        }
        $priority = $this->hits($text, explode(',', $settings['priority_keywords']));
        $excluded = $this->hits($text, explode(',', $settings['excluded_keywords'])) > 0;
        $age = $article->published_at ? max(0, $article->published_at->diffInDays(now(), false)) : null;
        $criteria = [
            'novelty' => min(100, 25 + 25 * $this->hits($text, ['nouveau', 'nouvelle', 'annonce', 'launch', 'release', 'introduc', 'dévoile', 'breakthrough'])),
            'importance' => min(100, 30 + 25 * $this->hits($text, ['majeur', 'critical', 'critique', 'vulnerability', 'mondial', 'million', 'first', 'premier'])),
            'impact' => min(100, 20 + 20 * count($categories)),
            'usefulness' => min(100, 25 + 20 * $priority + 15 * $this->hits($text, ['developer', 'développeur', 'tutorial', 'guide', 'open source', 'framework'])),
            'originality' => 50, // Unknown without comparing reporting: explicitly neutral.
            'reliability' => $article->source->reliability,
            'freshness' => $age === null ? 30 : ($age <= 2 ? 100 : ($age <= 7 ? 70 : ($age <= 30 ? 40 : 10))),
        ];
        $weightTotal = array_sum($settings['weights']);
        $score = 0;
        foreach ($criteria as $key => $value) {
            $score += $value * ($settings['weights'][$key] ?? 0) / max(1, $weightTotal);
        }
        $score = $excluded ? 0 : min(100, max(0, (int) round($score)));
        $summary = Str::limit($article->description ?: $article->title, 650);
        $explanation = $excluded ? 'Un mot-clé exclu a été détecté.' : count($categories).' catégorie(s) détectée(s), '.$priority.' mot(s)-clé(s) prioritaire(s).';
        $explanation .= ' Score heuristique local ; originalité non évaluée (valeur neutre).';
        if ($age === null) {
            $explanation .= ' Date de publication inconnue.';
        }
        DB::transaction(function () use ($article, $settings, $criteria, $score, $summary, $explanation, $categories): void {
            $article->analysis()->updateOrCreate(['article_id' => $article->id], [
                'score' => $score,
                'verdict' => $score >= (int) ($settings['threshold'] ?? 70) ? RelevanceVerdict::Relevant : RelevanceVerdict::Irrelevant,
                'summary' => $summary, 'summary_language' => $article->language,
                'explanation' => $explanation, 'criteria_scores' => $criteria, 'method' => 'local',
                'rules_version' => self::VERSION, 'configuration_hash' => hash('sha256', json_encode($settings, JSON_THROW_ON_ERROR)),
                'analyzed_at' => now(),
            ]);
            $article->categories()->sync($categories);
            $article->update(['analysis_status' => AnalysisStatus::Completed, 'analysis_error' => null]);
        });
    }

    /** @param array<int, string> $words */
    private function hits(string $text, array $words): int
    {
        return count(array_filter($words, static function (string $word) use ($text): bool {
            $word = mb_strtolower(trim($word));

            return $word !== '' && preg_match('/(?<![\pL\pN])'.preg_quote($word, '/').'(?![\pL\pN])/u', $text) === 1;
        }));
    }
}
