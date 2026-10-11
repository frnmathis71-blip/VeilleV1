<?php

namespace App\Services\Collection;

use App\Contracts\ArticleAnalyzer;
use App\Enums\AnalysisStatus;
use App\Enums\CollectionStatus;
use App\Enums\RelevanceVerdict;
use App\Models\Article;
use App\Models\CollectionRun;
use App\Models\CollectionRunItem;
use App\Models\CollectionRunSource;
use App\Models\RssSource;
use App\Services\Analysis\AnalyzerUnavailable;
use App\Services\Feeds\FeedEntry;
use App\Services\Feeds\FeedFetcher;
use App\Services\Feeds\FeedParser;
use App\Services\Feeds\PreviewImageFinder;
use App\Services\WatchSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class Collector
{
    /** Cache key holding the timestamp until which the analyzer must not be called. */
    public const ANALYZER_PAUSE_KEY = 'watch-analyzer-paused-until';

    /** Articles left unanalyzed by earlier collections and re-queued by a new one. */
    private const BACKLOG_LIMIT = 500;

    public function __construct(
        private FeedFetcher $fetcher,
        private FeedParser $parser,
        private ArticleAnalyzer $analyzer,
        private WatchSettings $settings,
        private PreviewImageFinder $previews,
    ) {}

    public function request(?int $userId = null, string $trigger = 'manual'): CollectionRun
    {
        $lock = Cache::lock('watch-request', 10);
        if (! $lock->get()) {
            throw new RuntimeException('Une demande de collecte est déjà en cours.');
        }
        try {
            $existing = CollectionRun::whereIn('status', ['pending', 'running'])->first();
            if ($existing) {
                return $existing;
            }

            return DB::transaction(function () use ($userId, $trigger): CollectionRun {
                $sources = RssSource::where('active', true)->get();
                $run = CollectionRun::create([
                    'trigger' => $trigger, 'requested_by' => $userId,
                    'configuration_snapshot' => $this->settings->all(), 'sources_count' => $sources->count(),
                ]);
                foreach ($sources as $source) {
                    $run->sources()->create(['rss_source_id' => $source->id]);
                }
                // No other run is active: articles still waiting for a verdict (AI unavailable, failure) get another chance.
                $backlog = Article::whereIn('analysis_status', ['pending', 'processing', 'failed'])->orderBy('id')->limit(self::BACKLOG_LIMIT)->pluck('id');
                foreach ($backlog as $articleId) {
                    $run->items()->create(['article_id' => $articleId]);
                }

                return $run;
            });
        } finally {
            $lock->release();
        }
    }

    /** Process a bounded slice. Persisted entries keep feed changes from losing a partial batch. */
    public function process(): ?CollectionRun
    {
        $lock = Cache::lock('watch-processing', 120);
        if (! $lock->get()) {
            return null;
        }
        try {
            $run = CollectionRun::whereIn('status', ['pending', 'running'])->oldest('id')->first();
            if (! $run) {
                return null;
            }
            $began = microtime(true);
            $run->update(['status' => CollectionStatus::Running, 'started_at' => $run->started_at ?? now(), 'heartbeat_at' => now()]);
            $settings = $run->configuration_snapshot;
            $limit = max(1, min(100, (int) $settings['batch_size']));
            $processed = 0;
            // Imported articles take priority, including an item interrupted on the previous invocation.
            $due = $run->items()->whereIn('status', ['pending', 'processing'])
                ->where(fn ($q) => $q->whereNull('retry_at')->orWhere('retry_at', '<=', now()))
                ->orderBy('id')->limit($limit)->get();
            foreach ($due as $item) {
                if (! $this->analyze($item, $settings)) {
                    break; // Analyzer paused: keep the remaining items for a later invocation.
                }
                $processed++;
                if (microtime(true) - $began > 25) {
                    break;
                }
            }
            foreach ($run->sources()->whereIn('status', ['pending', 'running'])->orderBy('id')->get() as $state) {
                if ($processed >= $limit || microtime(true) - $began > 25) {
                    break;
                }
                $source = RssSource::find($state->rss_source_id);
                if (! $source || ! $source->active) {
                    $state->update(['status' => CollectionStatus::Completed, 'finished_at' => now(), 'entries' => null]);

                    continue;
                }
                if ($state->entries === null) {
                    if ($state->retry_at?->isFuture()) {
                        continue;
                    }
                    // Shared throttle also covers manual requests and source tests.
                    if (! Cache::add('watch-host:'.hash('sha256', (string) parse_url($source->url, PHP_URL_HOST)), true, 10)) {
                        continue;
                    }
                    try {
                        $state->update(['status' => CollectionStatus::Running, 'started_at' => $state->started_at ?? now(), 'attempts' => $state->attempts + 1]);
                        $source->update(['last_attempt_at' => now()]);
                        $entries = $this->parser->parse($this->fetcher->fetch($source->url));
                        $state->update(['entries' => array_map(fn (FeedEntry $entry) => $entry->toArray(), $entries), 'discovered_count' => count($entries), 'error' => null, 'retry_at' => null]);
                        $source->update(['last_success_at' => now(), 'last_error' => null]);
                    } catch (Throwable $e) {
                        $state->update([
                            'status' => $state->attempts >= 3 ? CollectionStatus::Failed : CollectionStatus::Pending,
                            'error' => $this->safeError($e), 'retry_at' => now()->addMinutes(5),
                            'finished_at' => $state->attempts >= 3 ? now() : null,
                        ]);
                        $source->update(['last_error' => $this->safeError($e)]);
                        Log::warning('watch.feed_failed', ['run_id' => $run->id, 'source_id' => $source->id, 'exception' => $e::class]);

                        continue;
                    }
                }
                $entries = $state->entries ?? [];
                while ($state->cursor < count($entries) && $processed < $limit && microtime(true) - $began <= 25) {
                    $entry = FeedEntry::fromArray($entries[$state->cursor]);
                    $item = $this->import($run, $state, $source, $entry);
                    if ($item) {
                        $this->analyze($item, $settings);
                    }
                    $processed++;
                }
                if ($state->cursor >= count($entries)) {
                    $state->update(['status' => CollectionStatus::Completed, 'finished_at' => now(), 'entries' => null]);
                }
            }
            $this->refreshReport($run, (int) round((microtime(true) - $began) * 1000));

            return $run->fresh();
        } finally {
            $lock->release();
        }
    }

    private function import(CollectionRun $run, CollectionRunSource $state, RssSource $source, FeedEntry $entry): ?CollectionRunItem
    {
        return DB::transaction(function () use ($run, $state, $source, $entry): ?CollectionRunItem {
            $canonical = FeedParser::canonical($entry->url);
            $urlHash = hash('sha256', $canonical);
            $guidHash = $entry->guid ? hash('sha256', $entry->guid) : null;
            $contentHash = mb_strlen($entry->description) >= 300 ? hash('sha256', mb_strtolower($entry->title.' '.$entry->description)) : null;
            $query = Article::where('canonical_url_hash', $urlHash);
            if ($guidHash) {
                $query->orWhere(fn ($q) => $q->where('rss_source_id', $source->id)->where('guid_hash', $guidHash));
            }
            if ($contentHash) {
                $query->orWhere('content_hash', $contentHash);
            }
            $article = $query->first();
            $duplicate = $article !== null;
            if ($article && $article->image_url === null && $entry->imageUrl !== null) {
                $article->update(['image_url' => $entry->imageUrl]); // The feed now provides an illustration.
            }
            if (! $article) {
                $published = null;
                try {
                    $published = $entry->publishedAt ? Carbon::parse($entry->publishedAt)->utc() : null;
                } catch (Throwable) {
                    // An invalid date is unknown, never silently replaced with today.
                }
                $article = Article::create([
                    'rss_source_id' => $source->id, 'title' => $entry->title, 'url' => $entry->url,
                    'canonical_url_hash' => $urlHash, 'guid' => $entry->guid, 'guid_hash' => $guidHash,
                    'content_hash' => $contentHash, 'description' => $entry->description,
                    'author' => $entry->author, 'image_url' => $entry->imageUrl,
                    'language' => $entry->language ?: $source->language, 'published_at' => $published,
                    'collected_at' => now(), 'analysis_status' => AnalysisStatus::Pending,
                ]);
            }
            $item = null;
            if ($article->analysis_status !== AnalysisStatus::Completed) {
                $item = $run->items()->firstOrCreate(['article_id' => $article->id]);
            }
            $state->update([
                'cursor' => $state->cursor + 1,
                'created_count' => $state->created_count + ($duplicate ? 0 : 1),
                'duplicate_count' => $state->duplicate_count + ($duplicate ? 1 : 0),
            ]);

            return $item;
        });
    }

    public function analyzerPausedUntil(): ?Carbon
    {
        $until = (int) Cache::get(self::ANALYZER_PAUSE_KEY, 0);

        return $until > now()->getTimestamp() ? Carbon::createFromTimestamp($until) : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return bool false when the analyzer is paused and no further item should be attempted now
     */
    private function analyze(CollectionRunItem $item, array $settings): bool
    {
        if ($item->retry_at?->isFuture() || $item->status === AnalysisStatus::Completed) {
            return true;
        }
        if ($this->analyzerPausedUntil()) {
            return false; // The item stays pending without consuming an attempt.
        }
        $article = $item->article;
        $item->update(['status' => AnalysisStatus::Processing, 'reserved_at' => now(), 'attempts' => $item->attempts + 1]);
        try {
            $this->analyzer->analyze($article, $settings);
            $item->update(['status' => AnalysisStatus::Completed, 'finished_at' => now(), 'error' => null, 'retry_at' => null]);
        } catch (AnalyzerUnavailable $e) {
            // Not the article's fault: pause every analysis, keep the article pending for a later retry.
            Cache::put(self::ANALYZER_PAUSE_KEY, now()->addSeconds($e->retryAfter)->getTimestamp(), $e->retryAfter);
            $giveUp = $e->deferToNextRun || $item->attempts >= 3;
            $message = $e->getMessage().($giveUp ? ' Nouvelle tentative à la prochaine collecte.' : '');
            $item->update([
                'status' => $giveUp ? AnalysisStatus::Failed : AnalysisStatus::Pending,
                'error' => $message, 'retry_at' => now()->addSeconds(max(60, $e->retryAfter)),
                'finished_at' => $giveUp ? now() : null,
            ]);
            if ($e->deferToNextRun) {
                // Same cause for every waiting article: let the run finish, the next one re-queues them.
                $item->run->items()->whereIn('status', ['pending', 'processing'])
                    ->update(['status' => AnalysisStatus::Failed->value, 'error' => $message, 'finished_at' => now()]);
            }
            $article->update(['analysis_status' => AnalysisStatus::Pending, 'analysis_error' => $message]);
            Log::warning('watch.analyzer_unavailable', ['article_id' => $article->id, 'retry_after' => $e->retryAfter, 'message' => $e->getMessage()]);

            return false;
        } catch (Throwable $e) {
            $failed = $item->attempts >= 3;
            $item->update(['status' => $failed ? AnalysisStatus::Failed : AnalysisStatus::Pending, 'error' => $this->safeError($e), 'retry_at' => now()->addMinutes(5)]);
            $article->update(['analysis_status' => $failed ? AnalysisStatus::Failed : AnalysisStatus::Pending, 'analysis_error' => $this->safeError($e)]);
            Log::warning('watch.analysis_failed', ['article_id' => $article->id, 'exception' => $e::class]);
        }

        return true;
    }

    /**
     * Finds a preview image for articles whose feed gave none, within a time budget.
     * Each article is checked once, success or not; requests to one site are spaced by a second.
     */
    public function fillPreviewImages(int $limit = 15, float $budgetSeconds = 20, ?callable $onFailure = null): int
    {
        if (! config('watch.preview_images')) {
            return 0;
        }
        $lock = Cache::lock('watch-previews', 120);
        if (! $lock->get()) {
            return 0;
        }
        try {
            $began = microtime(true);
            $lastHit = [];
            $found = 0;
            $candidates = Article::whereNull('image_url')
                ->where(fn ($q) => $q->whereNull('image_checked_at')
                    // Failures are retried every 6 hours while the article is less than 3 days old.
                    ->orWhere(fn ($retry) => $retry->where('image_checked_at', '<', now()->subHours(6))->where('collected_at', '>', now()->subDays(3))))
                ->orderByRaw('image_checked_at is not null')->latest('id')->limit($limit)->get();
            foreach ($candidates as $article) {
                if (microtime(true) - $began > $budgetSeconds) {
                    break;
                }
                $host = (string) parse_url($article->url, PHP_URL_HOST);
                $wait = 1.0 - (microtime(true) - ($lastHit[$host] ?? 0));
                if ($wait > 0) {
                    usleep((int) ($wait * 1_000_000));
                }
                $lastHit[$host] = microtime(true);
                $failure = null;
                try {
                    $image = $this->previews->find($article->url);
                    $failure = $image === null ? 'Aucune image d’aperçu (og:image) déclarée par la page.' : null;
                } catch (Throwable $e) {
                    $image = null;
                    $failure = $e->getMessage();
                    Log::info('watch.preview_failed', ['article_id' => $article->id, 'exception' => $e::class, 'message' => $failure]);
                }
                if ($failure !== null && $onFailure !== null) {
                    $onFailure($article, $failure);
                }
                $article->update(['image_url' => $image, 'image_checked_at' => now()]);
                $found += $image !== null ? 1 : 0;
            }

            return $found;
        } finally {
            $lock->release();
        }
    }

    private function refreshReport(CollectionRun $run, int $duration): void
    {
        $errors = $run->sources()->where('status', 'failed')->count() + $run->items()->where('status', 'failed')->count();
        $pending = $run->sources()->whereIn('status', ['pending', 'running'])->exists()
            || $run->items()->whereIn('status', ['pending', 'processing'])->exists();
        $run->update([
            'discovered_count' => $run->sources()->sum('discovered_count'),
            'created_count' => $run->sources()->sum('created_count'),
            'duplicate_count' => $run->sources()->sum('duplicate_count'),
            'analyzed_count' => $run->items()->where('status', 'completed')->count(),
            'interesting_count' => $run->items()->where('status', 'completed')->whereHas('article.analysis', fn ($q) => $q->where('verdict', RelevanceVerdict::Relevant))->count(),
            'error_count' => $errors,
            'duration_ms' => $run->duration_ms + $duration,
            'heartbeat_at' => now(),
            'status' => $pending ? CollectionStatus::Running : ($errors ? CollectionStatus::CompletedWithErrors : CollectionStatus::Completed),
            'finished_at' => $pending ? null : now(),
        ]);
    }

    private function safeError(Throwable $e): string
    {
        return $e instanceof RuntimeException || $e instanceof \InvalidArgumentException
            ? mb_substr($e->getMessage(), 0, 500)
            : 'Erreur de traitement. Consultez les journaux techniques.';
    }
}
