<?php

namespace App\Services;

use App\Enums\RelevanceVerdict;
use App\Models\Article;
use App\Models\ArticleAnalysis;
use App\Models\ArticleReview;
use App\Models\Category;
use App\Models\RssSource;
use Carbon\CarbonImmutable;

class Statistics
{
    /** @return array<string, mixed> */
    public function forUser(int $userId): array
    {
        $today = CarbonImmutable::now('Europe/Paris')->startOfDay();
        $start = $today->subDays(29)->utc();
        $relevantVerdict = fn ($q) => $q->where('verdict', RelevanceVerdict::Relevant);
        $labels = [];
        $collected = $interesting = $validated = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = $today->subDays($i)->format('Y-m-d');
            $labels[$date] = $today->subDays($i)->format('d/m');
            $collected[$date] = $interesting[$date] = $validated[$date] = 0;
        }
        // Stream only relevant columns; date bucketing is identical on SQLite and MySQL.
        foreach (Article::where('collected_at', '>=', $start)->select('id', 'collected_at')->cursor() as $article) {
            $day = $article->collected_at->timezone('Europe/Paris')->format('Y-m-d');
            if (isset($collected[$day])) {
                $collected[$day]++;
            }
        }
        foreach (ArticleAnalysis::where('analyzed_at', '>=', $start)->where('verdict', RelevanceVerdict::Relevant)->select('id', 'analyzed_at')->cursor() as $analysis) {
            $day = $analysis->analyzed_at->timezone('Europe/Paris')->format('Y-m-d');
            if (isset($interesting[$day])) {
                $interesting[$day]++;
            }
        }
        foreach (ArticleReview::where('user_id', $userId)->where('decision', 'relevant')->where('decided_at', '>=', $start)->cursor() as $review) {
            $day = $review->decided_at->timezone('Europe/Paris')->format('Y-m-d');
            if (isset($validated[$day])) {
                $validated[$day]++;
            }
        }
        $reviews = ArticleReview::where('user_id', $userId);
        $relevant = (clone $reviews)->where('decision', 'relevant')->count();
        $rejected = (clone $reviews)->where('decision', 'rejected')->count();
        $recommendations = (clone $reviews)->whereHas('article.analysis', $relevantVerdict);
        $accepted = (clone $recommendations)->where('decision', 'relevant')->count();
        $declined = (clone $recommendations)->where('decision', 'rejected')->count();

        return [
            'collected' => Article::count(),
            'today' => Article::where('collected_at', '>=', $today->utc())->count(),
            'week' => Article::where('collected_at', '>=', $today->subDays(6)->utc())->count(),
            'analyzed' => ArticleAnalysis::count(),
            'analyzed_today' => ArticleAnalysis::where('analyzed_at', '>=', $today->utc())->count(),
            'pending' => Article::whereIn('analysis_status', ['pending', 'processing'])->count(),
            'interesting' => Article::withVerdict(RelevanceVerdict::Relevant)->count(),
            'irrelevant' => Article::withVerdict(RelevanceVerdict::Irrelevant)->count(),
            'unread' => Article::withVerdict(RelevanceVerdict::Relevant)->whereDoesntHave('reviews', fn ($q) => $q->where('user_id', $userId)->whereNotNull('read_at'))->count(),
            'interesting_today' => $interesting[$today->format('Y-m-d')],
            'relevant' => $relevant, 'rejected' => $rejected,
            'relevance_rate' => $relevant + $rejected ? round(100 * $relevant / ($relevant + $rejected)) : null,
            'quality_rate' => $accepted + $declined ? round(100 * $accepted / ($accepted + $declined)) : null,
            'accepted' => $accepted, 'declined' => $declined,
            'series' => ['labels' => array_values($labels), 'collected' => array_values($collected), 'interesting' => array_values($interesting), 'validated' => array_values($validated)],
            'categories' => Category::withCount('articles')->orderByDesc('articles_count')->get(),
            'sources' => RssSource::withTrashed()->withCount('articles')->orderByDesc('articles_count')->limit(10)->get(),
        ];
    }
}
