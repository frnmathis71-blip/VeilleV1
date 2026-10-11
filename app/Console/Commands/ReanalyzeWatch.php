<?php

namespace App\Console\Commands;

use App\Enums\AnalysisStatus;
use App\Models\Article;
use Illuminate\Console\Command;

class ReanalyzeWatch extends Command
{
    protected $signature = 'veille:reanalyze {--all : Tous les articles, et pas seulement ceux jugés par l’analyse locale} {--dry-run : Compter sans modifier}';

    protected $description = 'Remettre des articles en attente pour que l’analyseur actuel (Gemini) les juge à la prochaine collecte';

    public function handle(): int
    {
        $query = Article::where('analysis_status', AnalysisStatus::Completed);
        if (! $this->option('all')) {
            $query->whereHas('analysis', fn ($q) => $q->where('method', 'local'));
        }
        $count = $query->count();
        if ($this->option('dry-run')) {
            $this->info($count.' article(s) seraient réanalysés.');

            return self::SUCCESS;
        }
        // The current verdict stays visible until the new analysis replaces it.
        $query->update(['analysis_status' => AnalysisStatus::Pending->value, 'analysis_error' => null]);
        $this->info($count.' article(s) remis en attente. Lancez « php artisan veille:collect » : chaque collecte en reprend jusqu’à 500.');

        return self::SUCCESS;
    }
}
