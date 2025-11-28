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
        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // e.g., 'pending_email', 'confirmed'
            $table->string('name'); // e.g., 'Validation email en cours'
            $table->string('color', 7)->default('#6B7280'); // Hex color for badge
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // System statuses cannot be deleted
            $table->boolean('is_active')->default(false); // Status requires action
            $table->boolean('is_final')->default(false); // Status is final (closed lead)
            $table->boolean('can_be_set_after_call')->default(false); // Can be set after a call
            $table->integer('order')->default(0); // Display order
            $table->timestamps();

            $table->index('slug');
            $table->index('is_system');
            $table->index('is_active');
            $table->index('is_final');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_statuses');
    }
};
