<?php

use App\Enums\AnalysisStatus;
use App\Enums\CollectionStatus;
use App\Enums\RelevanceVerdict;
use App\Livewire\ArticleDetail;
use App\Livewire\WatchConfiguration;
use App\Models\Article;
use App\Models\CollectionRun;
use App\Models\RssSource;
use App\Models\User;
use App\Services\Analysis\LocalAnalyzer;
use App\Services\Collection\Collector;
use App\Services\Feeds\FeedFetcher;
use App\Services\WatchSettings;
use Database\Seeders\CategorySeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config(['watch.analyzer' => 'gemini', 'services.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-test', 'services.gemini.per_minute' => 100]);
    $this->seed(CategorySeeder::class);
    RssSource::create(['name' => 'Flux IA', 'url' => 'https://example.com/feed', 'url_hash' => hash('sha256', 'https://example.com/feed'), 'active' => true, 'language' => 'en', 'reliability' => 80]);
});

function geminiFeed(): string
{
    return '<?xml version="1.0"?><rss version="2.0"><channel><title>Tech</title><link>https://example.com</link><description>Flux</description>'
        .'<item><title>PHP 9 released with new JIT</title><link>https://example.com/php9</link><guid>php9</guid><description>The PHP team ships a major version.</description></item>'
        .'<item><title>Black Friday: -50% on TVs</title><link>https://example.com/promo</link><guid>promo</guid><description>Best deals of the week.</description></item>'
        .'</channel></rss>';
}

/** @param array<string, mixed> $data */
function geminiReply(array $data): array
{
    return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => json_encode($data)]]], 'finishReason' => 'STOP']]];
}

function geminiVerdict(bool $relevant): array
{
    return geminiReply([
        'title_fr' => $relevant ? 'PHP 9 publié avec un nouveau JIT' : '« Black Friday : -50 % sur les téléviseurs »',
        'summary' => $relevant ? 'L’équipe PHP publie une version majeure.' : 'Promotions sur des téléviseurs.',
        'reason' => $relevant ? 'Nouvelle version d’un langage utilisé en développement web.' : 'Publicité sans contenu informatique.',
        'relevant' => $relevant,
        'score' => $relevant ? 88 : 5,
        'categories' => $relevant ? ['programmation', 'inconnue'] : [],
        'criteria' => ['novelty' => 90, 'importance' => 80, 'impact' => 70, 'usefulness' => 95],
    ]);
}

/** Answers according to the article title found in the request. */
function fakeGeminiByTitle(): void
{
    Http::fake(['generativelanguage.googleapis.com/*' => fn (Request $request) => Http::response(
        geminiVerdict(str_contains($request['contents'][0]['parts'][0]['text'], 'Titre : PHP 9')),
    )]);
}

function runGeminiCollection(): ?CollectionRun
{
    $collector = app(Collector::class);
    $collector->request();

    return $collector->process();
}

test('gemini sorts collected articles into relevant and irrelevant lists', function () {
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->once()->andReturn(geminiFeed());
    fakeGeminiByTitle();

    $run = runGeminiCollection();

    expect($run->status)->toBe(CollectionStatus::Completed)->and($run->analyzed_count)->toBe(2)->and($run->interesting_count)->toBe(1);
    $php = Article::where('title', 'like', 'PHP 9%')->first();
    $promo = Article::where('title', 'like', 'Black Friday%')->first();
    expect($php->analysis->verdict)->toBe(RelevanceVerdict::Relevant)
        ->and($php->analysis->method)->toBe('gemini')
        ->and($php->analysis->summary)->toBe('L’équipe PHP publie une version majeure.')
        ->and($php->analysis->summary_language)->toBe('fr')
        ->and($php->categories->pluck('slug')->all())->toBe(['programmation'])
        ->and($php->analysis->criteria_scores['reliability'])->toBe(80)
        ->and($promo->analysis->verdict)->toBe(RelevanceVerdict::Irrelevant);

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'test-key')
        && str_contains($request->url(), 'models/gemini-test:generateContent')
        && ! str_contains($request->url(), 'test-key')
        && $request['generationConfig']['responseMimeType'] === 'application/json'
        && str_contains($request['systemInstruction']['parts'][0]['text'], 'BTS SIO'));

    $user = User::factory()->create();
    $this->actingAs($user)->get(route('articles.interesting'))->assertSee('PHP 9 released')->assertDontSee('Black Friday');
    $this->get(route('articles.irrelevant'))->assertSee('Black Friday')->assertDontSee('PHP 9 released');
    $this->get(route('articles.show', $php))->assertSee('Pourquoi ce verdict ?')->assertSee('Nouvelle version d’un langage');
});

test('an irrelevant article can be rescued into the personal library', function () {
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->once()->andReturn(geminiFeed());
    fakeGeminiByTitle();
    runGeminiCollection();
    $promo = Article::where('title', 'like', 'Black Friday%')->first();

    $this->actingAs(User::factory()->create());
    Livewire::test(ArticleDetail::class, ['article' => $promo])->call('decide', 'relevant');

    $this->get(route('articles.library'))->assertSee('Black Friday');
    expect($promo->analysis->fresh()->verdict)->toBe(RelevanceVerdict::Irrelevant);
});

test('a gemini quota error pauses analysis and retries later without losing articles', function () {
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->once()->andReturn(geminiFeed());
    Http::fakeSequence('generativelanguage.googleapis.com/*')
        ->push(['error' => ['code' => 429, 'message' => 'Quota exceeded', 'details' => [['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '30s']]]], 429)
        ->push(geminiVerdict(true))
        ->push(geminiVerdict(false));

    $first = runGeminiCollection();

    Http::assertSentCount(1); // The second article is not sent while Gemini is paused.
    expect($first->status)->toBe(CollectionStatus::Running)->and($first->finished_at)->toBeNull()
        ->and(Article::count())->toBe(2)
        ->and(Article::where('analysis_status', 'pending')->count())->toBe(2)
        ->and(app(Collector::class)->analyzerPausedUntil())->not->toBeNull();
    $this->get('/')->assertRedirect(); // Pages stay usable during the pause.

    $this->travel(2)->minutes();
    $done = app(Collector::class)->process();

    Http::assertSentCount(3);
    expect($done->status)->toBe(CollectionStatus::Completed)
        ->and(Article::where('analysis_status', 'completed')->count())->toBe(2);
});

test('without an api key articles wait and the next collection analyzes them', function () {
    config(['services.gemini.key' => '']);
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->twice()->andReturn(geminiFeed());
    fakeGeminiByTitle();

    $run = runGeminiCollection();
    $this->travel(20)->minutes();
    $run = app(Collector::class)->process() ?? $run->fresh();

    Http::assertNothingSent();
    expect($run->status)->toBe(CollectionStatus::CompletedWithErrors)
        ->and(Article::where('analysis_status', 'pending')->count())->toBe(2)
        ->and(Article::first()->analysis_error)->toContain('GEMINI_API_KEY');

    config(['services.gemini.key' => 'test-key']);
    Cache::flush();
    $next = runGeminiCollection();

    expect($next->status)->toBe(CollectionStatus::Completed)->and($next->analyzed_count)->toBe(2)
        ->and(Article::withVerdict(RelevanceVerdict::Relevant)->count())->toBe(1)
        ->and(Article::withVerdict(RelevanceVerdict::Irrelevant)->count())->toBe(1);
});

test('an unreadable gemini answer is retried then reported as an article failure', function () {
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->once()->andReturn(geminiFeed());
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'pas du JSON']]], 'finishReason' => 'STOP']]])]);

    runGeminiCollection();
    $this->travel(6)->minutes();
    app(Collector::class)->process();
    $this->travel(6)->minutes();
    $run = app(Collector::class)->process();

    expect($run->status)->toBe(CollectionStatus::CompletedWithErrors)
        ->and(Article::where('analysis_status', 'failed')->count())->toBe(2)
        ->and(Article::first()->analysis_error)->toContain('illisible');
});

test('irrelevant articles are purged sooner unless a user kept them', function () {
    $source = RssSource::first();
    $user = User::factory()->create();
    foreach (['irrelevant-old', 'irrelevant-kept', 'irrelevant-recent', 'relevant-old'] as $key) {
        $article = Article::create(['rss_source_id' => $source->id, 'title' => $key, 'url' => 'https://example.com/'.$key, 'canonical_url_hash' => hash('sha256', $key), 'collected_at' => now()->subDays($key === 'irrelevant-recent' ? 5 : 40), 'analysis_status' => AnalysisStatus::Completed]);
        $article->analysis()->create(['score' => 10, 'verdict' => str_starts_with($key, 'relevant') ? 'relevant' : 'irrelevant', 'summary' => 's', 'explanation' => 'e', 'criteria_scores' => [], 'method' => 'gemini', 'rules_version' => 'v', 'configuration_hash' => 'h', 'analyzed_at' => now()]);
        if ($key === 'irrelevant-kept') {
            $article->reviews()->create(['user_id' => $user->id, 'decision' => 'relevant']);
        }
    }

    $this->artisan('veille:prune')->assertSuccessful();

    expect(Article::orderBy('title')->pluck('title')->all())->toBe(['irrelevant-kept', 'irrelevant-recent', 'relevant-old']);
});

test('reanalyze command hands locally scored articles back to gemini', function () {
    $source = RssSource::first();
    $article = Article::create(['rss_source_id' => $source->id, 'title' => 'PHP 9 released', 'url' => 'https://example.com/php9', 'canonical_url_hash' => hash('sha256', 'php9'), 'collected_at' => now()]);
    app(LocalAnalyzer::class)->analyze($article, app(WatchSettings::class)->all());
    expect($article->fresh()->analysis->method)->toBe('local');

    $this->artisan('veille:reanalyze')->assertSuccessful();
    expect($article->fresh()->analysis_status)->toBe(AnalysisStatus::Pending);

    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->andReturn('<rss version="2.0"><channel><title>t</title></channel></rss>');
    fakeGeminiByTitle();
    runGeminiCollection();

    expect($article->fresh()->analysis->method)->toBe('gemini')->and($article->fresh()->analysis->verdict)->toBe(RelevanceVerdict::Relevant);
});

test('settings page saves the ai profile and new pages are reachable', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $this->actingAs($admin);

    Livewire::test(WatchConfiguration::class)
        ->set('settings.ai_profile', 'Uniquement la cybersécurité.')
        ->set('settings.irrelevant_retention_days', 15)
        ->call('save')->assertHasNoErrors();

    expect(app(WatchSettings::class)->all()['ai_profile'])->toBe('Uniquement la cybersécurité.');
    $this->get(route('watch.settings'))->assertOk()->assertSee('Tri par l’IA (Gemini)', false);
    $this->get(route('articles.irrelevant'))->assertOk()->assertSee('Articles impertinents');
});

test('titles are translated into French and the original stays visible', function () {
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->once()->andReturn(geminiFeed());
    fakeGeminiByTitle();
    runGeminiCollection();
    $php = Article::where('title', 'like', 'PHP 9%')->first();
    $promo = Article::where('title', 'like', 'Black Friday%')->first();

    expect($php->displayTitle())->toBe('PHP 9 publié avec un nouveau JIT')->and($php->isTitleTranslated())->toBeTrue()
        ->and($promo->analysis->title_fr)->toBe('Black Friday : -50 % sur les téléviseurs');

    $this->actingAs(User::factory()->create());
    $this->get(route('articles.interesting'))->assertSee('PHP 9 publié avec un nouveau JIT');
    $this->get(route('articles.show', $php))->assertSee('PHP 9 publié avec un nouveau JIT')->assertSee('Titre original : PHP 9 released with new JIT');
    $this->get(route('articles.all', ['search' => 'nouveau JIT']))->assertSee('PHP 9 publié');
});

test('a collection that runs too long closes and leaves unanalyzed articles to the next one', function () {
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->twice()->andReturn(geminiFeed());
    $quotaExceeded = true;
    Http::fake(['generativelanguage.googleapis.com/*' => function (Request $request) use (&$quotaExceeded) {
        return $quotaExceeded
        ? Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded']], 429)
        : Http::response(geminiVerdict(str_contains($request['contents'][0]['parts'][0]['text'], 'Titre : PHP 9')));
    }]);

    $run = runGeminiCollection();
    expect($run->status)->toBe(CollectionStatus::Running);

    $this->travel(41)->minutes();
    $closed = app(Collector::class)->process();

    expect($closed->status)->toBe(CollectionStatus::Completed)->and($closed->finished_at)->not->toBeNull()
        ->and($closed->deferred_count)->toBe(2)->and($closed->analyzed_count)->toBe(0)
        ->and(Article::where('analysis_status', 'pending')->count())->toBe(2);

    Cache::flush();
    $quotaExceeded = false;
    $next = runGeminiCollection();

    expect($next->status)->toBe(CollectionStatus::Completed)->and($next->analyzed_count)->toBe(2)->and($next->deferred_count)->toBe(0);
});

test('excluded keywords and outdated articles are rejected without calling gemini', function () {
    $old = now()->subDays(20)->toRfc7231String();
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->once()->andReturn('<?xml version="1.0"?><rss version="2.0"><channel><title>Tech</title><link>https://example.com</link><description>Flux</description>'
        .'<item><title>Bon plan : SSD à prix cassé</title><link>https://example.com/ssd</link><guid>ssd</guid><description>Offre limitée.</description></item>'
        .'<item><title>PHP 8 released</title><link>https://example.com/php8</link><guid>php8</guid><description>Old news.</description><pubDate>'.$old.'</pubDate></item>'
        .'</channel></rss>');
    fakeGeminiByTitle();

    $run = runGeminiCollection();

    Http::assertNothingSent();
    expect($run->status)->toBe(CollectionStatus::Completed)
        ->and(Article::withVerdict(RelevanceVerdict::Irrelevant)->count())->toBe(2)
        ->and(Article::where('url', 'like', '%ssd')->first()->analysis->explanation)->toContain('bon plan')
        ->and(Article::where('url', 'like', '%php8')->first()->analysis->explanation)->toContain('7 jours');
});

test('only articles above the selection threshold and not already covered are kept', function () {
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->once()->andReturn(geminiFeed());
    $reply = fn (array $overrides) => geminiReply([
        'title_fr' => 't', 'summary' => 's', 'reason' => 'Pertinent.', 'relevant' => true, 'already_covered' => false, 'score' => 88,
        'categories' => [], 'criteria' => ['novelty' => 90, 'importance' => 80, 'impact' => 70, 'usefulness' => 95, 'originality' => 60], ...$overrides,
    ]);
    Http::fake(['generativelanguage.googleapis.com/*' => fn (Request $request) => Http::response(
        str_contains($request['contents'][0]['parts'][0]['text'], 'Titre : PHP 9')
            ? $reply(['already_covered' => true])
            : $reply(['score' => 45, 'criteria' => ['novelty' => 40, 'importance' => 40, 'impact' => 40, 'usefulness' => 40, 'originality' => 40]]),
    )]);

    $run = runGeminiCollection();

    expect($run->interesting_count)->toBe(0);
    $php = Article::where('title', 'like', 'PHP 9%')->first();
    $promo = Article::where('title', 'like', 'Black Friday%')->first();
    expect($php->analysis->verdict)->toBe(RelevanceVerdict::Irrelevant)->and($php->analysis->explanation)->toContain('déjà couvert')
        ->and($promo->analysis->verdict)->toBe(RelevanceVerdict::Irrelevant)->and($promo->analysis->explanation)->toContain('inférieur au seuil');
});

test('gemini sees the articles already selected to avoid keeping one story twice', function () {
    $this->mock(FeedFetcher::class)->shouldReceive('fetch')->once()->andReturn(geminiFeed());
    fakeGeminiByTitle();

    runGeminiCollection();

    // PHP 9 (most recent first in order of analysis: same date, so feed order) is selected before the promo is judged.
    Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], 'Titre : Black Friday')
        && str_contains($request['contents'][0]['parts'][0]['text'], '- PHP 9 publié avec un nouveau JIT'));
});
