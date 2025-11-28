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
        Schema::create('mailwizz_imported_leads', function (Blueprint $table) {
            $table->id();
            $table->string('mailwizz_subscriber_id')->unique()->comment('ID unique du subscriber MailWizz');
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('email')->index();
            $table->timestamp('imported_at');
            $table->json('mailwizz_data')->nullable()->comment('Données brutes de MailWizz');
            $table->timestamps();

            $table->index(['email', 'mailwizz_subscriber_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailwizz_imported_leads');
    }
};
