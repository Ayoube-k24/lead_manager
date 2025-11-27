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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // lead_stale, agent_performance, conversion_rate, etc.
            $table->json('conditions')->nullable();
            $table->decimal('threshold', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('notification_channels')->default('["in_app"]');
            $table->dateTime('last_triggered_at')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
