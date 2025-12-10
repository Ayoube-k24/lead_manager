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
        Schema::create('role_alert_types', function (Blueprint $table) {
            $table->id();
            $table->string('role_slug'); // super_admin, call_center_owner, supervisor, agent
            $table->string('alert_type'); // lead_stale, status_threshold, agent_performance, etc.
            $table->string('name'); // Nom affiché pour ce type d'alerte
            $table->text('description')->nullable(); // Description du type d'alerte
            $table->boolean('is_enabled')->default(true); // Actif par défaut
            $table->json('default_conditions')->nullable(); // Conditions par défaut pour ce type
            $table->integer('order')->default(0); // Ordre d'affichage
            $table->timestamps();

            $table->unique(['role_slug', 'alert_type']);
            $table->index('role_slug');
            $table->index('is_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_alert_types');
    }
};
