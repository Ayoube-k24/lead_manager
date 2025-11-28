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
        Schema::create('mailwizz_configs', function (Blueprint $table) {
            $table->id();
            $table->string('api_url');
            $table->string('public_key');
            $table->text('private_key')->comment('Encrypted private key');
            $table->string('list_uid')->nullable()->comment('MailWizz list UID');
            $table->foreignId('call_center_id')->nullable()->constrained('call_centers')->nullOnDelete();
            $table->integer('import_frequency')->default(15)->comment('Fréquence en minutes (15, 30, 60, etc.)');
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_import_at')->nullable();
            $table->integer('last_import_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailwizz_configs');
    }
};
