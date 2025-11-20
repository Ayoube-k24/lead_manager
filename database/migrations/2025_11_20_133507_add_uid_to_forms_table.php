<?php

use App\Models\Form;
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
        Schema::table('forms', function (Blueprint $table) {
            $table->string('uid', 12)->nullable()->after('id');
        });

        Form::withoutEvents(function (): void {
            Form::whereNull('uid')
                ->chunkById(100, function ($forms): void {
                    foreach ($forms as $form) {
                        $form->uid = Form::generateUid();
                        $form->save();
                    }
                });
        });

        Schema::table('forms', function (Blueprint $table): void {
            $table->unique('uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropUnique('forms_uid_unique');
            $table->dropColumn('uid');
        });
    }
};
