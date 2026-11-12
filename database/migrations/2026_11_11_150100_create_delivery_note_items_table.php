<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 09 §F11 + plan 05 — Crea la tabla `delivery_note_items`.
 *
 * Esquema paralelo a `invoice_items` pero con un campo `quantity`
 * validado ≥ 0 (plan 05 §1) y un campo `unit` para la unidad de
 * entrega (e.g. kg, m², box) que se conserva del catálogo.
 *
 * Campos comerciales reducidos: NO guardamos `price` ni `total`
 * calculado (esos viven en la cabecera `delivery_notes`). El
 * albarán es por definición un documento sin importes monetarios
 * por defecto (plan 05 §1); los precios se conservan en `notes`
 * o se trasladan a la `Invoice` padre al convertir.
 */
class CreateDeliveryNoteItemsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('delivery_note_items')) {
            Schema::create('delivery_note_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('delivery_note_id')->nullable();
                $table->unsignedInteger('item_id')->nullable();
                $table->unsignedInteger('company_id')->nullable();

                $table->string('name');
                $table->text('description')->nullable();
                $table->string('unit')->nullable();
                $table->decimal('quantity', 15, 2)->default(0);

                // Snapshot comercial para la conversión a Invoice.
                $table->string('price')->nullable();
                $table->string('discount_type')->nullable();
                $table->string('discount_val')->nullable();
                $table->string('discount')->nullable();
                $table->string('tax')->nullable();
                $table->string('total')->nullable();
                $table->string('exchange_rate')->default('1');
                $table->string('base_price')->nullable();
                $table->string('base_discount_val')->nullable();
                $table->string('base_tax')->nullable();
                $table->string('base_total')->nullable();

                $table->timestamps();

                $table->foreign('delivery_note_id')
                    ->references('id')->on('delivery_notes')
                    ->onDelete('cascade');
                $table->foreign('item_id')
                    ->references('id')->on('items');
                $table->foreign('company_id')
                    ->references('id')->on('companies');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('delivery_note_items');
    }
}
