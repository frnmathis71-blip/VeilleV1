<?php

namespace App\Console\Commands;

use App\Models\RssSource;
use App\Services\Feeds\FeedFetcher;
use App\Services\Feeds\FeedParser;
use Illuminate\Console\Command;

class ImportSources extends Command
{
    protected $signature = 'veille:import-sources';

    protected $description = 'Vérifier les flux proposés avant de les enregistrer';

    public function handle(FeedFetcher $fetcher, FeedParser $parser): int
    {
        $sources = [
            ['Numerama', 'https://www.numerama.com/feed/', 'fr'],
            ['Frandroid', 'https://www.frandroid.com/feed', 'fr'],
            ['TechCrunch', 'https://techcrunch.com/feed/', 'en'],
            ['BleepingComputer', 'https://www.bleepingcomputer.com/feed/', 'en'],
        ];
        foreach ($sources as [$name, $url, $language]) {
            try {
                $entries = $parser->parse($fetcher->fetch($url));
                if (count($entries) === 0) {
                    $this->warn($name.' : aucune entrée, non ajouté.');

                    continue;
                }
                RssSource::firstOrCreate(['url_hash' => hash('sha256', $url)], [
                    'name' => $name, 'url' => $url, 'language' => $language,
                    'active' => true, 'reliability' => 70,
                ]);
                $this->info($name.' : vérifié, '.count($entries).' entrées.');
            } catch (\Throwable $e) {
                $this->warn($name.' : non ajouté — '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
