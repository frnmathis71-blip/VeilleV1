<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\RssSource;
use App\Models\User;
use App\Services\Feeds\FeedFetcher;
use App\Services\Feeds\FeedParser;
use App\Services\Feeds\PreviewImageFinder;
use Database\Seeders\CategorySeeder;

test('feed images come from media tags or the content but never from tracking pixels', function () {
    $xml = '<?xml version="1.0"?><rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/"><channel><title>T</title><link>https://e.com</link><description>d</description>'
        .'<item><title>A</title><link>https://e.com/a</link><enclosure url="https://e.com/a.jpg" type="image/jpeg" length="1"/></item>'
        .'<item><title>B</title><link>https://e.com/b</link><media:content url="https://e.com/b.webp" medium="image"/></item>'
        .'<item><title>C</title><link>https://e.com/c</link><description>&lt;img src="https://e.com/pixel.gif" width="1" height="1"/&gt;&lt;img src="https://e.com/c.png"&gt;</description></item>'
        .'<item><title>D</title><link>https://e.com/d</link><description>&lt;img src="javascript:alert(1)"&gt;</description><enclosure url="https://e.com/d.mp3" type="audio/mpeg" length="1"/></item>'
        .'</channel></rss>';

    $images = collect(app(FeedParser::class)->parse($xml))->pluck('imageUrl', 'title')->all();

    expect($images)->toBe(['A' => 'https://e.com/a.jpg', 'B' => 'https://e.com/b.webp', 'C' => 'https://e.com/c.png', 'D' => null]);
});

test('articles are shown as tiles with image, score, title and a consult button', function () {
    $this->seed(CategorySeeder::class);
    $source = RssSource::create(['name' => 'Korben', 'url' => 'https://korben.info/feed', 'url_hash' => hash('sha256', 'k'), 'active' => true, 'reliability' => 70]);
    $withImage = Article::create(['rss_source_id' => $source->id, 'title' => 'Article illustré', 'url' => 'https://e.com/1', 'canonical_url_hash' => hash('sha256', '1'), 'image_url' => 'https://e.com/1.jpg', 'collected_at' => now(), 'analysis_status' => 'completed']);
    $withImage->analysis()->create(['score' => 82, 'verdict' => 'relevant', 'summary' => 's', 'explanation' => 'e', 'criteria_scores' => [], 'method' => 'gemini', 'rules_version' => 'v', 'configuration_hash' => 'h', 'analyzed_at' => now()]);
    $withoutImage = Article::create(['rss_source_id' => $source->id, 'title' => 'Article sans image', 'url' => 'https://e.com/2', 'canonical_url_hash' => hash('sha256', '2'), 'collected_at' => now()]);
    $withoutImage->categories()->attach(Category::where('slug', 'cloud')->value('id'));

    $this->actingAs(User::factory()->create())->get(route('articles.all'))
        ->assertOk()
        ->assertSee('https://e.com/1.jpg', false)
        ->assertSee('82/100')
        ->assertSee('Article illustré')
        ->assertSee('En attente')
        ->assertSee('Cloud computing')
        ->assertSee(route('articles.show', $withoutImage), false)
        ->assertSee('Consulter');
});

test('the preview image declared by the page head is found and made absolute', function () {
    $head = '<html><head><meta name="twitter:image" content="https://cdn.e.com/tw.jpg">'
        .'<meta content="/uploads/une.jpg?w=1200&amp;h=630" property="og:image" /></head>';

    expect(PreviewImageFinder::extract($head, 'https://www.e.com/2026/article'))->toBe('https://www.e.com/uploads/une.jpg?w=1200&h=630')
        ->and(PreviewImageFinder::extract('<meta property="og:image" content="javascript:alert(1)">', 'https://e.com/a'))->toBeNull()
        ->and(PreviewImageFinder::extract('<meta name="twitter:image" content="https://e.com/t.png">', 'https://e.com/a'))->toBe('https://e.com/t.png');
});

test('articles without feed image get their preview image once, during collection', function () {
    $source = RssSource::create(['name' => 'Numerama', 'url' => 'https://numerama.com/feed', 'url_hash' => hash('sha256', 'n'), 'active' => true, 'reliability' => 70]);
    foreach (['avec', 'sans'] as $key) {
        Article::create(['rss_source_id' => $source->id, 'title' => $key, 'url' => 'https://numerama.com/'.$key, 'canonical_url_hash' => hash('sha256', $key), 'collected_at' => now()]);
    }
    $this->mock(FeedFetcher::class)->shouldReceive('fetchPageHead')->twice()
        ->andReturnUsing(fn (string $url) => str_ends_with($url, 'avec') ? '<meta property="og:image" content="https://numerama.com/une.jpg">' : throw new RuntimeException('HTTP 403'));

    $this->artisan('veille:images')->assertSuccessful();
    $this->artisan('veille:images')->assertSuccessful(); // Already checked: no new request.

    expect(Article::where('title', 'avec')->value('image_url'))->toBe('https://numerama.com/une.jpg')
        ->and(Article::where('title', 'sans')->value('image_url'))->toBeNull()
        ->and(Article::whereNull('image_checked_at')->count())->toBe(0);
});

test('emoji are removed from feed titles and excerpts', function () {
    $xml = '<?xml version="1.0"?><rss version="2.0"><channel><title>T</title><link>https://e.com</link><description>d</description>'
        .'<item><title>🔥 Bon plan : le Pixel 10 🚀 à prix cassé ✅</title><link>https://e.com/a</link><description>Vite 👉 ici ❤️ !</description></item></channel></rss>';

    $entry = app(FeedParser::class)->parse($xml)[0];

    expect($entry->title)->toBe('Bon plan : le Pixel 10 à prix cassé')->and($entry->description)->toBe('Vite ici !');
});
