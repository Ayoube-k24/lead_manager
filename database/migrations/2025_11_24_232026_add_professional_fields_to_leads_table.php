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
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedInteger('call_attempts')->default(0)->after('called_at');
            $table->unsignedInteger('call_duration')->nullable()->after('call_attempts')->comment('Duration in seconds');
            $table->timestamp('next_call_at')->nullable()->after('call_duration');
            $table->timestamp('last_call_at')->nullable()->after('next_call_at');
            $table->timestamp('status_changed_at')->nullable()->after('last_call_at');
            $table->string('phone')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'call_attempts',
                'call_duration',
                'next_call_at',
                'last_call_at',
                'status_changed_at',
                'phone',
            ]);
        });
    }
};
