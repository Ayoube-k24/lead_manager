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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('pending_email');
            $table->timestamp('email_confirmed_at')->nullable();
            $table->string('email_confirmation_token')->nullable();
            $table->timestamp('email_confirmation_token_expires_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('call_center_id')->nullable()->constrained('call_centers')->nullOnDelete();
            $table->text('call_comment')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
