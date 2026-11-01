<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('pet_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('vet_id')->constrained('users');
            $table->string('location', 8);
            $table->foreignId('address_id')->nullable()->constrained();
            $table->foreignId('zone_id')->nullable()->constrained('service_zones');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 16)->index();
            // Snapshots: later price changes never touch an existing booking.
            $table->unsignedBigInteger('service_price_rials')->nullable();
            $table->unsignedBigInteger('home_visit_fee_rials')->default(0);
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'starts_at']);
        });

        // The last word on double booking, whatever happens to holds in the cache.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
            EXCLUDE USING gist (vet_id WITH =, tsrange(starts_at, ends_at) WITH &&)
            WHERE (status <> 'cancelled')");
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
