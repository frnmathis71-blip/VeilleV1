<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_analyses', function (Blueprint $table) {
            // French title given by the AI analyzer (identical to the original when already in French).
            $table->text('title_fr')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('article_analyses', fn (Blueprint $table) => $table->dropColumn('title_fr'));
    }
};
