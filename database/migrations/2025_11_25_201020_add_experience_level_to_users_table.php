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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('experience_level', ['beginner', 'intermediate', 'advanced'])
                ->default('beginner')
                ->after('is_active')
                ->comment('Niveau d\'expérience de l\'agent: beginner, intermediate, advanced');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('experience_level');
        });
    }
};
