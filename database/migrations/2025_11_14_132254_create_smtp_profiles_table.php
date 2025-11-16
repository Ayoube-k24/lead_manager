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
        if (Schema::hasTable('smtp_profiles')) {
            Schema::dropIfExists('smtp_profiles');
        }

        Schema::create('smtp_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->integer('port');
            $table->string('encryption')->default('tls')->comment('tls, ssl, none');
            $table->string('username');
            $table->text('password'); // Will be encrypted
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smtp_profiles');
    }
};
