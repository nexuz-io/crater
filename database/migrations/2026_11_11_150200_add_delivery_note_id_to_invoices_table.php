<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 09 §F11 + plan 05 §3 — añade `invoices.delivery_note_id`.
 *
 * Restricciones:
 *
 * - **Nullable UNIQUE**: una Invoice puede tener 0 o 1 DeliveryNote
 *   de origen. La unicidad evita que un albarán genere dos
 *   facturas (constraint clave para idempotencia bajo doble click).
 *
 * - **`onDelete('set null')`**: si se borra el albarán, la Invoice
 *   sobrevive con `delivery_note_id = null` — preserva historial
 *   comercial y contable.
 *
 * - **Index compuesto**: `(company_id, delivery_note_id)` para
 *   optimizar las consultas que filtran por empresa + traceability.
 */
class AddDeliveryNoteIdToInvoicesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'delivery_note_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('delivery_note_id')->nullable();
                $table->foreign('delivery_note_id')
                    ->references('id')->on('delivery_notes')
                    ->onDelete('set null');
                $table->unique('delivery_note_id', 'invoices_delivery_note_id_uniq');
                $table->index(['company_id', 'delivery_note_id'], 'invoices_company_delivery_note_idx');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'delivery_note_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropUnique('invoices_delivery_note_id_uniq');
                $table->dropIndex('invoices_company_delivery_note_idx');
                $table->dropForeign(['delivery_note_id']);
                $table->dropColumn('delivery_note_id');
            });
        }
    }
}
