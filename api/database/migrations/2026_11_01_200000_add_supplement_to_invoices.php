<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A fee added after the original invoice was frozen gets its own invoice, linked here.
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('supplement_of_id')->nullable()->constrained('invoices');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplement_of_id');
        });
    }
};
