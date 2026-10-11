<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
        Schema::create('rss_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('url');
            $table->char('url_hash', 64)->unique();
            $table->boolean('active')->default(true)->index();
            $table->string('language', 20)->nullable();
            $table->unsignedTinyInteger('reliability')->default(50);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->text('etag')->nullable();
            $table->text('last_modified')->nullable();
            $table->timestamp('next_fetch_at')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('keywords')->nullable();
            $table->boolean('is_monitored')->default(true);
            $table->timestamps();
        });
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rss_source_id')->constrained()->restrictOnDelete();
            $table->text('title');
            $table->text('url');
            $table->char('canonical_url_hash', 64)->unique();
            $table->text('guid')->nullable();
            $table->char('guid_hash', 64)->nullable();
            $table->char('content_hash', 64)->nullable()->index();
            $table->text('author')->nullable();
            $table->longText('description')->nullable();
            $table->longText('available_content')->nullable();
            $table->text('image_url')->nullable();
            $table->string('language', 20)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('collected_at');
            $table->string('analysis_status', 30)->default('pending');
            $table->text('analysis_error')->nullable();
            $table->timestamps();
            $table->unique(['rss_source_id', 'guid_hash']);
            $table->index(['collected_at', 'id']);
            $table->index(['published_at', 'id']);
            $table->index(['rss_source_id', 'published_at']);
            $table->index(['analysis_status', 'id']);
        });
        Schema::create('article_category', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'category_id']);
        });
        Schema::create('article_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('summary');
            $table->string('summary_language', 20)->nullable();
            $table->text('explanation');
            $table->json('criteria_scores');
            $table->string('method', 30);
            $table->string('rules_version', 50);
            $table->char('configuration_hash', 64);
            $table->timestamp('analyzed_at')->index();
            $table->timestamps();
            $table->index(['score', 'article_id']);
        });
        Schema::create('article_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('decision', 30)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('saved_for_later_at')->nullable();
            $table->text('personal_note')->nullable();
            $table->timestamps();
            $table->unique(['article_id', 'user_id']);
            $table->index(['user_id', 'decision', 'decided_at']);
            $table->index(['user_id', 'read_at']);
        });
        Schema::create('collection_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 30)->default('pending')->index();
            $table->string('trigger', 30);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->json('configuration_snapshot');
            foreach (['sources_count', 'discovered_count', 'created_count', 'duplicate_count', 'analyzed_count', 'interesting_count', 'error_count', 'duration_ms'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            $table->text('error')->nullable();
            $table->timestamps();
        });
        Schema::create('collection_run_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rss_source_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('retry_at')->nullable();
            foreach (['discovered_count', 'created_count', 'duplicate_count'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['collection_run_id', 'rss_source_id']);
        });
        Schema::create('collection_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('retry_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['collection_run_id', 'article_id']);
            $table->index(['status', 'retry_at']);
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'collection_run_items', 'collection_run_sources', 'collection_runs', 'article_reviews', 'article_analyses', 'article_category', 'articles', 'categories', 'rss_sources'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
