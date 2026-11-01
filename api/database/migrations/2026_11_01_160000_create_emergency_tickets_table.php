<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('pet_id')->nullable()->constrained();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 16)->index();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 500)->nullable();
            $table->timestamps();
        });

        // A repeat tap must never open a second ticket.
        DB::statement("CREATE UNIQUE INDEX emergency_one_open_per_owner ON emergency_tickets (owner_id) WHERE status <> 'resolved'");
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_tickets');
    }
};
