<?php

use App\Enums\CollectionStatus;
use App\Models\Article;
use App\Models\CollectionRun;
use App\Models\RssSource;
use App\Services\Feeds\FeedFetcher;
use App\Services\WatchSettings;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['watch.analyzer' => 'gemini', 'services.gemini.key' => 'test-key', 'services.gemini.per_minute' => 100, 'watch.preview_images' => false]);
    RssSource::create(['name' => 'Flux', 'url' => 'https://example.com/feed', 'url_hash' => hash('sha256', 'f'), 'active' => true, 'reliability' => 70]);
});

function hourlyFeed(int $count): string
{
    $items = '';
    for ($i = 1; $i <= $count; $i++) {
        $items .= '<item><title>Article '.$i.'</title><link>https://example.com/a'.$i.'</link><guid>a'.$i.'</guid><description>Texte '.$i.'</description></item>';
    }

    return '<?xml version="1.0"?><rss version="2.0"><channel><title>T</title><link>https://example.com</link><description>d</description>'.$items.'</channel></rss>';
}

test('one hourly pass collects and analyzes everything, beyond a single batch', function () {
    $settings = app(WatchSettings::class);
    $settings->save([...$settings->all(), 'batch_size' => 5]);
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->once()->andReturn(hourlyFeed(12));
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
        'summary' => 'Résumé.', 'reason' => 'Raison.', 'relevant' => true, 'score' => 80, 'categories' => [],
        'criteria' => ['novelty' => 50, 'importance' => 50, 'impact' => 50, 'usefulness' => 50],
    ])]]], 'finishReason' => 'STOP']]])]);

    $this->artisan('veille:cron --minutes=2')->assertSuccessful();

    expect(CollectionRun::first()->status)->toBe(CollectionStatus::Completed)
        ->and(Article::where('analysis_status', 'completed')->count())->toBe(12);

    // An hour later the collection is not due yet: nothing new is requested.
    $this->travel(1)->hours();
    $this->artisan('veille:cron --minutes=1')->assertSuccessful();
    expect(CollectionRun::count())->toBe(1);
});

test('planned collections can be turned off', function () {
    $settings = app(WatchSettings::class);
    $settings->save([...$settings->all(), 'enabled' => false]);

    $this->artisan('veille:cron --minutes=1')->assertSuccessful();

    expect(CollectionRun::count())->toBe(0);
});
