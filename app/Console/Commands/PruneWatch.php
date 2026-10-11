<?php

namespace App\Console\Commands;

use App\Enums\RelevanceVerdict;
use App\Models\Article;
use App\Models\CollectionRunItem;
use App\Services\WatchSettings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class PruneWatch extends Command
{
    protected $signature = 'veille:prune {--dry-run : Compter sans supprimer}';

    protected $description = 'Purger les anciens articles et les impertinents sans validation, note ou marque-page';

    public function handle(WatchSettings $settings): int
    {
        $config = $settings->all();
        $irrelevantCutoff = now()->subDays((int) $config['irrelevant_retention_days']);
        $query = Article::where(fn (Builder $q) => $q->where('collected_at', '<', now()->subDays((int) $config['retention_days']))
            // Articles judged irrelevant by the analyzer are kept for a shorter time, for checking only.
            ->orWhere(fn (Builder $old) => $old->where('collected_at', '<', $irrelevantCutoff)->whereHas('analysis', fn ($a) => $a->where('verdict', RelevanceVerdict::Irrelevant))))
            ->whereDoesntHave('reviews', fn ($q) => $q->where('decision', 'relevant')->orWhereNotNull('saved_for_later_at')->orWhere(fn ($note) => $note->whereNotNull('personal_note')->where('personal_note', '!=', '')))
            ->whereNotIn('id', CollectionRunItem::whereIn('status', ['pending', 'processing'])->select('article_id'));
        if ($this->option('dry-run')) {
            $this->info($query->count().' articles éligibles.');
        } else {
            $ids = $query->orderBy('id')->limit(500)->pluck('id');
            $count = Article::whereIn('id', $ids)->delete();
            $this->info($count.' articles supprimés (maximum 500 par passage).');
        }

        return self::SUCCESS;
    }
}
