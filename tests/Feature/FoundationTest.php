<?php

use App\Enums\AnalysisStatus;
use App\Enums\ReviewDecision;
use App\Models\Article;
use App\Models\ArticleReview;
use App\Models\Category;
use App\Models\RssSource;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

function foundationArticle(): Article
{
    $source = RssSource::create(['name' => 'Test RSS', 'url' => 'https://example.com/feed', 'url_hash' => hash('sha256', 'https://example.com/feed')]);

    return Article::create(['rss_source_id' => $source->id, 'title' => 'Actualité de test', 'url' => 'https://example.com/article', 'canonical_url_hash' => hash('sha256', 'https://example.com/article'), 'collected_at' => now()]);
}

test('category seeding is idempotent and does not create demonstration accounts', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);
    expect(Category::count())->toBe(15)->and(User::count())->toBe(0)->and(Article::count())->toBe(0);
});

test('administrator creation hashes password and grants verified private access', function () {
    $this->artisan('veille:create-admin')
        ->expectsQuestion('Nom', 'Administrateur')
        ->expectsQuestion('Adresse e-mail', 'ADMIN@example.com')
        ->expectsQuestion('Mot de passe (12 caractères minimum)', 'Secret-robuste123!')
        ->expectsQuestion('Confirmer le mot de passe', 'Secret-robuste123!')
        ->expectsOutput('Administrateur créé. Vous pouvez vous connecter.')
        ->assertSuccessful();
    $user = User::sole();
    expect($user->email)->toBe('admin@example.com')
        ->and($user->is_admin)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('Secret-robuste123!', $user->password))->toBeTrue();
    expect(Gate::forUser($user)->allows('manage-watch'))->toBeTrue();
});

test('weak administrator passwords create no account', function () {
    $this->artisan('veille:create-admin')
        ->expectsQuestion('Nom', 'Admin')
        ->expectsQuestion('Adresse e-mail', 'admin@example.com')
        ->expectsQuestion('Mot de passe (12 caractères minimum)', 'short')
        ->expectsQuestion('Confirmer le mot de passe', 'short')
        ->assertFailed();
    expect(User::count())->toBe(0);
});

test('non interactive administrator creation is refused', function () {
    $this->artisan('veille:create-admin', ['--no-interaction' => true])->assertFailed();
    expect(User::count())->toBe(0);
});

test('reviews are personal and automatic analysis does not validate relevance', function () {
    $article = foundationArticle();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $article->analysis()->create([
        'score' => 90, 'summary' => 'Extrait', 'explanation' => 'Règles locales',
        'criteria_scores' => [], 'method' => 'local', 'rules_version' => '1',
        'configuration_hash' => hash('sha256', 'test'), 'analyzed_at' => now(),
    ]);
    $article->update(['analysis_status' => AnalysisStatus::Completed]);
    $review = $article->reviews()->create(['user_id' => $user->id, 'decision' => ReviewDecision::Relevant, 'decided_at' => now()]);
    expect(Gate::forUser($other)->allows('update', $review))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $review))->toBeTrue()
        ->and(Gate::forUser($user)->allows('manage-watch'))->toBeFalse()
        ->and($other->reviews()->count())->toBe(0);
    $this->actingAs($other)->get(route('dashboard'))->assertOk()->assertViewHas('relevant', 0)->assertViewHas('analyzed', 1);
    $this->actingAs($user)->get(route('dashboard'))->assertViewHas('relevant', 1)->assertViewHas('collected', 1);
});

test('article source remains accessible after archival', function () {
    $article = foundationArticle();
    $article->source->delete();
    expect($article->fresh()->source)->not->toBeNull()->and(RssSource::count())->toBe(0);
});

test('database prevents duplicate canonical URLs', function () {
    $article = foundationArticle();
    $copy = $article->replicate();
    expect(fn () => $copy->save())->toThrow(QueryException::class);
});

test('database prevents two reviews by one user for the same article', function () {
    $article = foundationArticle();
    $user = User::factory()->create();
    ArticleReview::create(['article_id' => $article->id, 'user_id' => $user->id]);
    expect(fn () => ArticleReview::create(['article_id' => $article->id, 'user_id' => $user->id]))->toThrow(QueryException::class);
});
