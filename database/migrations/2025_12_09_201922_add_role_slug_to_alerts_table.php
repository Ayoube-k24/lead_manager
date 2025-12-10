<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('role_slug')->nullable()->after('user_id');
            $table->index(['role_slug', 'is_active']);
        });

        // Migrer les alertes existantes pour leur attribuer le rôle de leur utilisateur
        DB::statement('
            UPDATE alerts 
            SET role_slug = (
                SELECT roles.slug 
                FROM users 
                JOIN roles ON users.role_id = roles.id 
                WHERE users.id = alerts.user_id
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['role_slug', 'is_active']);
            $table->dropColumn('role_slug');
        });
    }
};
