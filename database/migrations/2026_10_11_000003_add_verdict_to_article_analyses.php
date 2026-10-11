<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_analyses', function (Blueprint $table) {
            $table->string('verdict', 20)->nullable()->after('score');
            $table->index(['verdict', 'article_id']);
        });

        // Existing local analyses keep the classification they had: score compared with the current threshold.
        $stored = DB::table('settings')->where('key', 'watch')->value('value');
        $values = is_string($stored) ? json_decode($stored, true) : null;
        $values = is_string($values) ? json_decode($values, true) : $values;
        $threshold = (int) (is_array($values) && isset($values['threshold']) ? $values['threshold'] : config('watch.defaults.threshold', 70));
        DB::table('article_analyses')->where('score', '>=', $threshold)->update(['verdict' => 'relevant']);
        DB::table('article_analyses')->where('score', '<', $threshold)->update(['verdict' => 'irrelevant']);
    }

    public function down(): void
    {
        Schema::table('article_analyses', function (Blueprint $table) {
            $table->dropIndex(['verdict', 'article_id']);
            $table->dropColumn('verdict');
        });
    }
};
