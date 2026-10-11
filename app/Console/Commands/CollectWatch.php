<?php

namespace App\Console\Commands;

use App\Services\Collection\Collector;
use App\Services\WatchSettings;
use Illuminate\Console\Command;

class CollectWatch extends Command
{
    protected $signature = 'veille:collect {--scheduled : Respecter la fréquence configurée} {--process-only : Traiter uniquement les demandes en attente}';

    protected $description = 'Collecter et analyser un lot borné, reprenable au prochain appel';

    public function handle(Collector $collector, WatchSettings $settings): int
    {
        if (! $this->option('process-only') && (! $this->option('scheduled') || $settings->collectionDue())) {
            $collector->request(trigger: $this->option('scheduled') ? 'scheduled' : 'command');
        }
        $run = $collector->process();
        $images = $collector->fillPreviewImages();
        if ($images > 0) {
            $this->info($images.' image(s) d’aperçu trouvée(s).');
        }
        $this->info($run ? 'Collecte #'.$run->id.' : '.$run->status->value.' — '.$run->created_count.' nouveaux, '.$run->analyzed_count.' analysés.' : 'Aucune opération disponible (ou un traitement est déjà en cours).');

        return self::SUCCESS;
    }
}
