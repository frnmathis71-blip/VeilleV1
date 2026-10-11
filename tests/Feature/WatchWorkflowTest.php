<?php

use App\Enums\AnalysisStatus;
use App\Enums\CollectionStatus;
use App\Livewire\ArticleDetail;
use App\Livewire\History;
use App\Models\Article;
use App\Models\ArticleReview;
use App\Models\RssSource;
use App\Models\User;
use App\Services\Analysis\LocalAnalyzer;
use App\Services\Collection\Collector;
use App\Services\Feeds\FeedFetcher;
use App\Services\Feeds\FeedParser;
use App\Services\Feeds\PublicUrl;
use App\Services\WatchSettings;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

function watchFeed(): string
{
    return '<?xml version="1.0"?><rss version="2.0"><channel><title>Tech</title><link>https://example.com</link><description>Flux</description><language>fr</language><item><title>Nouveau framework open source pour développeur</title><link>https://example.com/news?utm_source=rss</link><guid>article-1</guid><description>Annonce majeure : guide du nouveau framework Laravel et base de données MySQL.</description><pubDate>Sun, 11 Oct 2026 08:00:00 GMT</pubDate></item><item><title>Cybersécurité : nouvelle vulnérabilité critique</title><link>https://example.com/security</link><guid>article-2</guid><description>Une vulnérabilité critique affecte un million de serveurs.</description></item></channel></rss>';
}

function watchSource(): RssSource
{
    return RssSource::create(['name' => 'Flux test', 'url' => 'https://example.com/feed', 'url_hash' => hash('sha256', 'https://example.com/feed'), 'active' => true, 'language' => 'fr', 'reliability' => 80])->fresh();
}

function watchAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_admin' => true])->save();

    return $user;
}

test('RSS and Atom are parsed without external HTML execution', function () {
    $parser = app(FeedParser::class);
    $items = $parser->parse(watchFeed());
    expect($items)->toHaveCount(2)->and(collect($items)->pluck('title')->join(' '))->toContain('framework');
    $atom = '<feed xmlns="http://www.w3.org/2005/Atom"><title>Atom</title><id>urn:feed</id><updated>2026-10-11T10:00:00Z</updated><entry><id>urn:1</id><title>Article Atom</title><link href="https://example.com/atom"/><updated>2026-10-11T10:00:00Z</updated><summary type="html">&lt;script&gt;alert(1)&lt;/script&gt;&lt;b&gt;Texte sûr&lt;/b&gt;</summary></entry></feed>';
    $entry = $parser->parse($atom)[0];
    expect($entry->url)->toBe('https://example.com/atom')->and($entry->description)->not->toContain('<script');
    expect(fn () => $parser->parse('<!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss/>'))->toThrow(RuntimeException::class);
    expect(FeedParser::canonical('https://example.com/news?utm_source=rss&id=5#section'))->toBe('https://example.com/news?id=5');
    expect(FeedParser::safeLink('javascript:alert(1)'))->toBeNull();
});

test('SSRF guard rejects local addresses credentials non HTTP and mixed DNS answers', function () {
    $guard = app(PublicUrl::class);
    foreach (['http://127.0.0.1/feed', 'http://[::1]/', 'file:///etc/passwd', 'https://user:pass@example.com/', 'http://foo.local/', 'http://example.com:8080/rss', 'http://2130706433/'] as $url) {
        expect(fn () => $guard->validate($url))->toThrow(InvalidArgumentException::class);
    }
    foreach (['127.0.0.1', '10.1.1.1', '169.254.169.254', '100.64.1.1', '198.18.0.1', '224.1.1.1', '192.168.1.1', '::1'] as $ip) {
        expect($guard->isPublicIp($ip))->toBeFalse();
    }
    expect($guard->isPublicIp('93.184.216.34'))->toBeTrue();
});

test('collection resumes its persisted batch and never closes before all entries are analyzed', function () {
    $this->seed(CategorySeeder::class);
    watchSource();
    $settings = app(WatchSettings::class);
    $settings->save([...$settings->all(), 'batch_size' => 1]);
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->once()->andReturn(watchFeed());
    $collector = app(Collector::class);
    $run = $collector->request();
    expect($collector->request()->id)->toBe($run->id);
    $first = $collector->process();
    expect($first->status)->toBe(CollectionStatus::Running)->and($first->finished_at)->toBeNull()->and(Article::count())->toBe(1);
    $second = $collector->process();
    expect($second->status)->toBe(CollectionStatus::Completed)->and($second->created_count)->toBe(2)->and($second->analyzed_count)->toBe(2);
    expect(ArticleReview::count())->toBe(0)->and(Article::where('analysis_status', 'completed')->count())->toBe(2);
});

test('repeated collections deduplicate URL and GUID without repeating analysis', function () {
    watchSource();
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->twice()->andReturn(watchFeed());
    $collector = app(Collector::class);
    $collector->request();
    $collector->process();
    Cache::flush();
    $collector->request();
    $run = $collector->process();
    expect(Article::count())->toBe(2)->and($run->duplicate_count)->toBe(2)->and($run->created_count)->toBe(0)->and($run->analyzed_count)->toBe(0);
});

test('failed feeds wait for retry then finish explicitly with errors', function () {
    watchSource();
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->times(3)->andThrow(new RuntimeException('Flux indisponible.'));
    $collector = app(Collector::class);
    $collector->request();
    $first = $collector->process();
    expect($first->status)->toBe(CollectionStatus::Running)->and($first->finished_at)->toBeNull();
    $this->travel(6)->minutes();
    $collector->process();
    $this->travel(6)->minutes();
    $last = $collector->process();
    expect($last->status)->toBe(CollectionStatus::CompletedWithErrors)->and($last->error_count)->toBe(1)->and($last->finished_at)->not->toBeNull();
});

test('processing lock prevents simultaneous collection', function () {
    watchSource();
    app(Collector::class)->request();
    $lock = Cache::lock('watch-processing', 120);
    $lock->get();
    expect(app(Collector::class)->process())->toBeNull()->and(Article::count())->toBe(0);
    $lock->release();
});

test('local scoring is bounded transparent and exclusions override recommendations', function () {
    $source = watchSource();
    $article = Article::create(['rss_source_id' => $source->id, 'title' => 'Bon plan nouveau framework', 'url' => 'https://example.com/a', 'canonical_url_hash' => hash('sha256', 'a'), 'collected_at' => now()]);
    $analyzer = app(LocalAnalyzer::class);
    $analyzer->analyze($article, app(WatchSettings::class)->all());
    $article->refresh();
    expect($article->analysis->score)->toBe(0)->and($article->analysis_status)->toBe(AnalysisStatus::Completed)->and($article->analysis->explanation)->toContain('exclu');
    $analyzedAt = $article->analysis->analyzed_at;
    $this->travel(1)->hours();
    $analyzer->analyze($article, app(WatchSettings::class)->all());
    expect($article->analysis->fresh()->analyzed_at->equalTo($analyzedAt))->toBeTrue();
});

test('all watch pages render and admin routes reject ordinary users', function () {
    $admin = watchAdmin();
    $this->actingAs($admin);
    foreach (['articles.all', 'articles.interesting', 'articles.library', 'sources', 'history', 'watch.settings', 'statistics'] as $route) {
        $this->get(route($route))->assertOk();
    }
    $this->actingAs(User::factory()->create())->get(route('sources'))->assertForbidden();
    Livewire::test(History::class)->call('launch')->assertForbidden();
});

test('personal decisions notes and read status can be changed without affecting other users', function () {
    watchSource();
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->once()->andReturn(watchFeed());
    $collector = app(Collector::class);
    $collector->request();
    $collector->process();
    $article = Article::first();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);
    Livewire::test(ArticleDetail::class, ['article' => $article])
        ->call('decide', 'relevant')->call('markRead')->call('toggleLater')
        ->set('note', 'Ma note privée')->call('saveNote')->assertHasNoErrors();
    $this->get(route('articles.library'))->assertSee($article->title);
    $this->actingAs($other)->get(route('articles.library'))->assertDontSee($article->title)->assertDontSee('Ma note privée');
    $this->actingAs($user);
    Livewire::test(ArticleDetail::class, ['article' => $article])->call('decide', 'clear');
    expect($user->reviews()->first()->decision)->toBeNull()->and($user->reviews()->first()->personal_note)->toBe('Ma note privée');
});

test('purge preserves relevant noted and saved articles', function () {
    $source = watchSource();
    $user = User::factory()->create();
    foreach (['relevant', 'note', 'later', 'discard'] as $key) {
        $article = Article::create(['rss_source_id' => $source->id, 'title' => $key, 'url' => 'https://example.com/'.$key, 'canonical_url_hash' => hash('sha256', $key), 'collected_at' => now()->subDays(200)]);
        if ($key !== 'discard') {
            $article->reviews()->create(['user_id' => $user->id, 'decision' => $key === 'relevant' ? 'relevant' : null, 'personal_note' => $key === 'note' ? 'À garder' : null, 'saved_for_later_at' => $key === 'later' ? now() : null]);
        }
    }
    $this->artisan('veille:prune')->assertSuccessful();
    expect(Article::count())->toBe(3);
});
