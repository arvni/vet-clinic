<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breeds', function (Blueprint $table) {
            $table->id();
            $table->string('species', 16);
            $table->string('name_fa');
            $table->string('name_en');
            $table->timestamps();

            $table->unique(['species', 'name_en']);
        });

        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('name', 80);
            $table->string('species', 16)->index();
            $table->foreignId('breed_id')->nullable()->constrained('breeds')->nullOnDelete();
            $table->string('breed_text', 80)->nullable()->comment('Free text when the breed is not in the list');
            $table->string('sex', 8)->default('unknown');
            $table->date('birth_date')->nullable();
            $table->boolean('birth_date_estimated')->default(false);
            $table->boolean('neutered')->nullable();
            $table->string('color', 80)->nullable();
            $table->string('microchip_no', 32)->nullable()->unique();
            $table->string('clinic_pet_id', 32)->nullable()->unique();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'deleted_at']);
        });

        Schema::create('pet_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('weight_grams');
            $table->timestamp('measured_at');
            $table->string('source', 8)->comment('owner | vet');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pet_id', 'measured_at']);
        });

        Schema::create('pet_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 8)->comment('drug | food | other');
            $table->string('substance', 120);
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('pet_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->comment('national | clinic_id | certificate | other');
            $table->string('number', 64)->nullable();
            $table->date('issued_at')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->boolean('verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // A national pet document number belongs to one live pet only (FR-1.7).
        DB::statement("CREATE UNIQUE INDEX pet_documents_national_number_unique ON pet_documents (number) WHERE kind = 'national' AND number IS NOT NULL AND deleted_at IS NULL");

        Schema::create('pet_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users');
            $table->string('to_mobile', 16)->index();
            $table->foreignId('to_user_id')->nullable()->constrained('users');
            $table->string('status', 12)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // At most one pending transfer per pet.
        DB::statement("CREATE UNIQUE INDEX pet_transfers_one_pending ON pet_transfers (pet_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_transfers');
        Schema::dropIfExists('pet_documents');
        Schema::dropIfExists('pet_allergies');
        Schema::dropIfExists('pet_weights');
        Schema::dropIfExists('pets');
        Schema::dropIfExists('breeds');
    }
};
