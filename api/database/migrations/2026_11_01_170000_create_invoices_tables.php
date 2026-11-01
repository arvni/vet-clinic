<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->string('key', 24)->primary()->comment('e.g. INV-1405');
            $table->unsignedBigInteger('last');
        });

        // FR-5.3: a rate change is a new row, so old invoices never move.
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32);
            $table->unsignedSmallInteger('basis_points');
            $table->date('effective_from');
            $table->timestamps();

            $table->unique(['category', 'effective_from']);
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->unsignedSmallInteger('basis_points')->nullable();
            $table->unsignedBigInteger('fixed_rials')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12)->default('invoice');
            $table->string('number', 24)->nullable()->unique();
            $table->foreignId('credit_of_id')->nullable()->constrained('invoices');
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('booking_id')->nullable()->constrained();
            $table->string('status', 16)->index();
            $table->unsignedBigInteger('subtotal_rials')->default(0);
            $table->foreignId('discount_code_id')->nullable()->constrained();
            $table->unsignedSmallInteger('discount_basis_points')->nullable();
            $table->unsignedBigInteger('discount_fixed_rials')->nullable();
            $table->string('discount_reason', 255)->nullable();
            $table->foreignId('discounted_by')->nullable()->constrained('users');
            $table->unsignedBigInteger('discount_rials')->default(0);
            $table->unsignedBigInteger('vat_rials')->default(0);
            $table->unsignedBigInteger('total_rials')->default(0);
            $table->unsignedBigInteger('paid_rials')->default(0);
            $table->unsignedBigInteger('credited_rials')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index('owner_id');
        });

        DB::statement("CREATE UNIQUE INDEX invoices_one_per_booking ON invoices (booking_id) WHERE kind = 'invoice' AND status <> 'cancelled'");

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained();
            $table->string('kind', 16);
            $table->foreignId('service_id')->nullable()->constrained();
            $table->string('description_fa', 255);
            $table->string('description_en', 255)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_rials');
            $table->unsignedSmallInteger('vat_basis_points')->default(0);
            $table->unsignedBigInteger('discount_rials')->default(0);
            // On a credit note: the original line reversed. Unique, so a line is credited once.
            $table->foreignId('credits_line_id')->nullable()->unique()->constrained('invoice_lines');
            $table->timestamps();
        });

        // FR-5.4: issued documents are immutable, whatever the application tries.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION invoices_freeze_issued() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.status <> 'draft' THEN
                        RAISE EXCEPTION 'issued invoice % cannot be deleted', OLD.id;
                    END IF;
                    RETURN OLD;
                END IF;
                IF OLD.status <> 'draft' AND (
                    NEW.number IS DISTINCT FROM OLD.number OR NEW.kind IS DISTINCT FROM OLD.kind
                    OR NEW.owner_id IS DISTINCT FROM OLD.owner_id OR NEW.booking_id IS DISTINCT FROM OLD.booking_id
                    OR NEW.subtotal_rials IS DISTINCT FROM OLD.subtotal_rials
                    OR NEW.discount_rials IS DISTINCT FROM OLD.discount_rials
                    OR NEW.vat_rials IS DISTINCT FROM OLD.vat_rials
                    OR NEW.total_rials IS DISTINCT FROM OLD.total_rials
                    OR NEW.issued_at IS DISTINCT FROM OLD.issued_at
                    OR NEW.status = 'draft'
                ) THEN
                    RAISE EXCEPTION 'issued invoice % is immutable', OLD.id;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS invoices_freeze_issued ON invoices;
            CREATE TRIGGER invoices_freeze_issued BEFORE UPDATE OR DELETE ON invoices
                FOR EACH ROW EXECUTE FUNCTION invoices_freeze_issued();

            CREATE OR REPLACE FUNCTION invoice_lines_freeze_issued() RETURNS trigger AS $$
            DECLARE parent_status text;
            BEGIN
                SELECT status INTO parent_status FROM invoices
                    WHERE id = COALESCE(NEW.invoice_id, OLD.invoice_id);
                IF parent_status IS DISTINCT FROM 'draft' THEN
                    RAISE EXCEPTION 'lines of an issued invoice are immutable';
                END IF;
                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS invoice_lines_freeze_issued ON invoice_lines;
            CREATE TRIGGER invoice_lines_freeze_issued BEFORE INSERT OR UPDATE OR DELETE ON invoice_lines
                FOR EACH ROW EXECUTE FUNCTION invoice_lines_freeze_issued();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('invoice_counters');
        DB::unprepared('DROP FUNCTION IF EXISTS invoices_freeze_issued(); DROP FUNCTION IF EXISTS invoice_lines_freeze_issued();');
    }
};
