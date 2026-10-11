<?php

use App\Services\Feeds\FeedParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Set once the article page has been checked for a preview image, found or not.
            $table->timestamp('image_checked_at')->nullable();
        });

        // Articles collected before the emoji filter keep a sober title and excerpt too.
        DB::table('articles')->select('id', 'title', 'description')->orderBy('id')->chunkById(200, function ($articles): void {
            foreach ($articles as $article) {
                $title = FeedParser::withoutEmoji((string) $article->title) ?: 'Article sans titre';
                $description = $article->description === null ? null : FeedParser::withoutEmoji((string) $article->description);
                if ($title !== $article->title || $description !== $article->description) {
                    DB::table('articles')->where('id', $article->id)->update(['title' => $title, 'description' => $description]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn('image_checked_at'));
    }
};
