<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\Collection\Collector;
use Illuminate\Console\Command;

class FindPreviewImages extends Command
{
    protected $signature = 'veille:images {--limit=100 : Nombre maximum d’articles à traiter} {--retry : Réessayer aussi les articles déjà vérifiés sans succès}';

    protected $description = 'Chercher l’image d’aperçu (og:image) des articles dont le flux ne fournit pas d’image';

    public function handle(Collector $collector): int
    {
        if (! config('watch.preview_images')) {
            $this->warn('Recherche désactivée : WATCH_PREVIEW_IMAGES=false.');

            return self::SUCCESS;
        }
        if ($this->option('retry')) {
            Article::whereNull('image_url')->update(['image_checked_at' => null]);
        }
        $limit = max(1, (int) $this->option('limit'));
        $found = $collector->fillPreviewImages($limit, $limit * 15, function ($article, string $reason): void {
            $this->warn('Sans image : '.mb_strimwidth($article->title, 0, 60, '…').' — '.$reason);
        });
        $this->info($found.' image(s) trouvée(s). Restent sans vérification : '.Article::whereNull('image_url')->whereNull('image_checked_at')->count().'.');

        return self::SUCCESS;
    }
}
