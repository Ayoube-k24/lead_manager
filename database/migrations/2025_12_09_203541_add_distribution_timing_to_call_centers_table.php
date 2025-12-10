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
        Schema::table('call_centers', function (Blueprint $table) {
            $table->string('distribution_timing')->default('after_email_confirmation')->after('distribution_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_centers', function (Blueprint $table) {
            $table->dropColumn('distribution_timing');
        });
    }
};
