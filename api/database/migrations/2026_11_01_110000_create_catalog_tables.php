<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_fa', 120);
            $table->string('name_en', 120)->nullable();
            $table->text('description_fa')->nullable();
            $table->text('description_en')->nullable();
            $table->string('icon', 40)->nullable();
            $table->string('category', 24);
            $table->unsignedSmallInteger('duration_minutes');
            $table->jsonb('locations')->comment('subset of clinic, home, online');
            $table->jsonb('species')->default('[]')->comment('empty = all species');
            $table->boolean('is_addon')->default(false)->comment('Can be added to another visit with one tap');
            $table->boolean('requires_prepayment')->default(false);
            $table->string('tax_category', 32)->nullable();
            $table->string('tax_goods_id', 32)->nullable()->comment('Moadian goods/service ID');
            $table->unsignedSmallInteger('sort')->default(100);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Effective-dated prices (FR-2.3): issued invoices keep the price they used.
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_rials');
            $table->timestamp('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['service_id', 'effective_from']);
        });

        Schema::create('vaccines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_fa', 120);
            $table->string('name_en', 120)->nullable();
            $table->string('manufacturer', 120);
            $table->jsonb('diseases')->comment('[{fa, en}] diseases covered');
            $table->jsonb('species');
            $table->unsignedSmallInteger('min_age_weeks')->default(0);
            $table->unsignedTinyInteger('primary_doses')->default(1);
            $table->unsignedSmallInteger('primary_interval_days')->nullable();
            $table->unsignedSmallInteger('booster_interval_days')->nullable();
            $table->unsignedBigInteger('vaccine_cost_rials');
            $table->unsignedBigInteger('service_fee_rials')->default(0);
            $table->unsignedBigInteger('injection_fee_rials')->default(0);
            $table->text('note_fa')->nullable()->comment('Why brands differ in price, shown to owners');
            $table->text('note_en')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccines');
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('services');
    }
};
