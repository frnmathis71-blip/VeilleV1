<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_runs', function (Blueprint $table) {
            // Articles left unanalyzed when the time limit closed the run; the next collection picks them up.
            $table->unsignedInteger('deferred_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('collection_runs', fn (Blueprint $table) => $table->dropColumn('deferred_count'));
    }
};
