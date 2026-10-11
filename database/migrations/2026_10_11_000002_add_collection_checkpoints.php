<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_run_sources', function (Blueprint $table) {
            $table->json('entries')->nullable();
            $table->unsignedInteger('cursor')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('collection_run_sources', fn (Blueprint $table) => $table->dropColumn(['entries', 'cursor']));
    }
};
