<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 09 §F11 + plan 05 — Crea la tabla `delivery_notes`.
 *
 * Esquema paralelo a `invoices` pero con campos específicos para
 * albaranes: `delivery_date`, `delivery_address` (texto libre,
 * independiente de la dirección del cliente), `status` con valores
 * disjuntos `DRAFT | DELIVERED | CANCELLED` (matriz plan 06
 * §paso-5 §3 columna DeliveryNote), y los campos comerciales
 * suficientes para la conversión a Invoice (sub_total, total,
 * tax, exchange_rate, base_*).
 *
 * Decisiones:
 *
 * - **Cantidad positiva**: plan 05 §1. La lógica se aplica en la
 *   request validation (no en la BD). El campo es `decimal(15,2)`
 *   para alinearse con `invoice_items.quantity`.
 *
 * - **`unique_hash`**: análogo a Invoice/Estimate, soporta URL
 *   pública del PDF vía Hashids.
 *
 * - **Indices**: (company_id, status), (company_id, delivery_date),
 *   (company_id, customer_id) para los listados típicos.
 *
 * - **`sequence_number` y `customer_sequence_number`**: opcionales
 *   (esta tabla no los tiene en `invoices` originales pero los
 *   añadimos para futura numeración).
 */
class CreateDeliveryNotesTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('delivery_notes')) {
            Schema::create('delivery_notes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('creator_id')->nullable();
                $table->unsignedInteger('company_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedInteger('currency_id')->nullable();

                $table->string('delivery_number')->nullable();
                $table->string('reference_number')->nullable();
                $table->string('template_name')->nullable();

                $table->date('delivery_date');
                $table->date('due_date')->nullable();
                $table->text('delivery_address')->nullable();
                $table->text('notes')->nullable();
                $table->text('terms_and_conditions')->nullable();

                $table->string('status')->default('DRAFT');

                $table->string('sub_total')->nullable();
                $table->string('total')->nullable();
                $table->string('tax')->nullable();
                $table->string('discount')->nullable();
                $table->string('discount_val')->nullable();
                $table->string('discount_type')->nullable();
                $table->string('tax_per_item')->default('NO');
                $table->string('discount_per_item')->default('NO');
                $table->string('sales_tax_type')->nullable();
                $table->string('sales_tax_address_type')->nullable();

                $table->string('exchange_rate')->default('1');
                $table->string('base_total')->nullable();
                $table->string('base_sub_total')->nullable();
                $table->string('base_tax')->nullable();
                $table->string('base_discount_val')->nullable();

                $table->string('unique_hash')->nullable();

                $table->integer('sequence_number')->nullable();
                $table->integer('customer_sequence_number')->nullable();

                $table->timestamps();

                // Indices por empresa + criterios de listado.
                $table->index(['company_id', 'status'], 'delivery_notes_company_status_idx');
                $table->index(['company_id', 'delivery_date'], 'delivery_notes_company_date_idx');
                $table->index(['company_id', 'customer_id'], 'delivery_notes_company_customer_idx');

                // FKs.
                $table->foreign('creator_id')
                    ->references('id')->on('users');
                $table->foreign('company_id')
                    ->references('id')->on('companies');
                $table->foreign('customer_id')
                    ->references('id')->on('customers');
                $table->foreign('currency_id')
                    ->references('id')->on('currencies');

                // delivery_number único por empresa — evita colisiones
                // en la numeración del albarán.
                $table->unique(['company_id', 'delivery_number'], 'delivery_notes_company_number_uniq');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('delivery_notes');
    }
}
