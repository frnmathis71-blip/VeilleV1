<?php

use App\Enums\CollectionStatus;
use App\Livewire\WatchConfiguration;
use App\Models\Article;
use App\Models\CollectionRun;
use App\Models\RssSource;
use App\Models\User;
use App\Services\Feeds\FeedFetcher;
use App\Services\WatchSettings;
use Carbon\CarbonImmutable;
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
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:15', 'Europe/Paris'));
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

    // An hour later the day's collection has already been done: nothing new is requested.
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

test('livewire and flux scripts are served as static files once published', function () {
    $this->artisan('veille:assets')->assertSuccessful();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('/vendor/flux/flux-lite.min.js', false)
        ->assertSee('/vendor/livewire/livewire', false)
        ->assertDontSee('/flux/flux.js', false)
        ->assertDontSee('/flux/flux.min.js', false);
});

test('the automatic collection starts once a day at the chosen hour', function () {
    $settings = app(WatchSettings::class);
    $settings->save([...$settings->all(), 'collect_hour' => 6]);
    Http::fake();
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->andReturn(hourlyFeed(0));

    $this->travelTo(CarbonImmutable::parse('2026-10-12 06:20', 'Europe/Paris'));
    expect($settings->collectionDue())->toBeTrue();
    $this->artisan('veille:cron --minutes=1')->assertSuccessful();
    expect(CollectionRun::count())->toBe(1)->and($settings->collectionDue())->toBeFalse()
        ->and($settings->nextPlannedAt()->format('Y-m-d H:i'))->toBe('2026-10-13 06:00');

    $this->travelTo(CarbonImmutable::parse('2026-10-13 05:20', 'Europe/Paris'));
    $this->artisan('veille:cron --minutes=1')->assertSuccessful();
    expect(CollectionRun::count())->toBe(1); // Not 6 o'clock yet.

    $this->travelTo(CarbonImmutable::parse('2026-10-13 06:20', 'Europe/Paris'));
    $this->artisan('veille:cron --minutes=1')->assertSuccessful();
    expect(CollectionRun::count())->toBe(2);
});

test('the collection hour is chosen in the watch settings', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $this->actingAs($admin);

    Livewire\Livewire::test(WatchConfiguration::class)
        ->set('settings.collect_hour', '22')->call('save')->assertHasNoErrors()->assertSee('22:00');

    expect((int) app(WatchSettings::class)->all()['collect_hour'])->toBe(22);
    $this->get(route('watch.settings'))->assertSee('Heure de la collecte automatique');
});
