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
        Schema::create('lead_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->string('attachment_path')->nullable()->comment('Chemin du fichier joint');
            $table->string('attachment_name')->nullable()->comment('Nom original du fichier');
            $table->string('attachment_mime')->nullable()->comment('Type MIME du fichier');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
            $table->index(['user_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_emails');
    }
};
