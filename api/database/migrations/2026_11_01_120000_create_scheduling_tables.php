<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Weekly pattern in clinic-local (Asia/Tehran) wall-clock times.
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->comment('0 = Sunday … 6 = Saturday');
            $table->string('kind', 8)->comment('work or break');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['user_id', 'weekday']);
        });

        // A null user_id closes the whole clinic; otherwise it is one vet's day off.
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name_fa', 120);
            $table->string('name_en', 120)->nullable();
            $table->timestamps();

            $table->unique(['date', 'user_id']);
        });

        // NULLs are distinct in a unique index, so clinic-wide days need their own.
        DB::statement('CREATE UNIQUE INDEX holidays_clinic_date_unique ON holidays (date) WHERE user_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('schedule_blocks');
    }
};
