<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('gateway', 16);
            $table->string('authority', 64)->unique();
            $table->unsignedBigInteger('amount_rials');
            $table->string('status', 16)->index();
            $table->string('ref_id', 64)->nullable();
            $table->string('card_pan', 32)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
