<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\CollectionRun;
use App\Services\Collection\Collector;
use App\Services\WatchSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Entry point for hosts whose cron can only run once an hour (OVHcloud web hosting).
 * One invocation does what the every-minute scheduler would do over the hour:
 * starts the collection when it is due, then chains batches until nothing is left or the time budget runs out.
 */
class HourlyWatch extends Command
{
    protected $signature = 'veille:cron {--minutes=45 : Durée maximale de traitement (la tâche OVH est arrêtée à 60 minutes)}';

    protected $description = 'Tâche horaire : collecte quotidienne, analyse, images et purge en un seul passage';

    public function handle(Collector $collector, WatchSettings $settings): int
    {
        // Two overlapping invocations would only wait for each other's locks: keep a single one.
        $lock = Cache::lock('watch-hourly', 3600);
        if (! $lock->get()) {
            $this->warn('Un passage précédent est encore en cours.');

            return self::SUCCESS;
        }
        try {
            $deadline = microtime(true) + 60 * max(1, min(55, (int) $this->option('minutes')));
            if ($settings->collectionDue()) {
                $run = $collector->request(trigger: 'scheduled');
                $this->info('Collecte #'.$run->id.' demandée.');
            }

            $batches = 0;
            while (microtime(true) < $deadline) {
                $before = $this->progress();
                $run = $collector->process();
                $images = $collector->fillPreviewImages(15, 20);
                $batches++;
                $active = CollectionRun::whereIn('status', ['pending', 'running'])->exists();
                $imagesLeft = config('watch.preview_images') && Article::whereNull('image_url')->whereNull('image_checked_at')->exists();
                if (! $active && ! $imagesLeft) {
                    break; // Everything is done until the next collection.
                }
                // Nothing moved (feed waiting for a retry, Gemini paused): wait instead of spinning.
                if ($this->progress() === $before && $images === 0) {
                    $pause = $collector->analyzerPausedUntil();
                    $wait = $pause ? max(5, $pause->getTimestamp() - time()) : 30;
                    sleep((int) min($wait, 120, max(1, $deadline - microtime(true))));
                }
                if ($run && $run->finished_at) {
                    $this->info('Collecte #'.$run->id.' terminée : '.$run->status->value.'.');
                }
            }
            $this->info($batches.' lot(s) traité(s).');

            // Daily purge, once per day whatever the hour of the first invocation.
            if (Cache::add('watch-pruned:'.now('Europe/Paris')->toDateString(), true, now()->addDays(2))) {
                $this->call('veille:prune');
            }
        } catch (Throwable $e) {
            report($e);
            $this->error('Erreur : '.$e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /** Fingerprint of the work done so far, to detect a batch that made no progress. */
    private function progress(): string
    {
        return implode(':', [
            Article::count(),
            Article::where('analysis_status', 'completed')->count(),
            CollectionRun::whereIn('status', ['pending', 'running'])->count(),
        ]);
    }
}
