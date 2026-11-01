<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Home-visit pricing zones (FR-5.2); the first match by sort wins where zones overlap.
        Schema::create('service_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name_fa', 120);
            $table->string('name_en', 120)->nullable();
            $table->jsonb('polygon')->comment('[[lat, lng], ...] outer ring, not closed');
            $table->unsignedBigInteger('home_visit_fee_rials');
            $table->unsignedSmallInteger('sort')->default(100);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('line', 500);
            $table->string('details', 255)->nullable();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('service_zones');
    }
};
