<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->integer('score')->default(0)->after('called_at');
            $table->dateTime('score_updated_at')->nullable()->after('score');
            $table->json('score_factors')->nullable()->after('score_updated_at');

            $table->index('score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['score']);
            $table->dropColumn(['score', 'score_updated_at', 'score_factors']);
        });
    }
};
