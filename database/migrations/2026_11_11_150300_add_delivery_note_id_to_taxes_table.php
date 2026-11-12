<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 09 §F11 + plan 05 §3 — añade `taxes.delivery_note_id`.
 *
 * La tabla `taxes` ya tiene FK-por-columna (invoice_id, estimate_id,
 * recurring_invoice_id) para soportar la naturaleza polimórfica
 * transversal de los taxes. F11 añade la cuarta FK para
 * DeliveryNote. Mantiene la convención tax-per-document.
 *
 * `onDelete('set null')` para preservar el tax si se borra el
 * albarán (la factura ya convertida sobrevive).
 *
 * NO se añade unicidad: una factura puede tener varios taxes
 * `delivery_note_id = null` (los previos a F11) y un albarán puede
 * tener varios taxes del header.
 */
class AddDeliveryNoteIdToTaxesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('taxes') && ! Schema::hasColumn('taxes', 'delivery_note_id')) {
            Schema::table('taxes', function (Blueprint $table) {
                $table->unsignedBigInteger('delivery_note_id')->nullable();
                $table->foreign('delivery_note_id')
                    ->references('id')->on('delivery_notes')
                    ->onDelete('set null');
                $table->index('delivery_note_id', 'taxes_delivery_note_idx');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('taxes') && Schema::hasColumn('taxes', 'delivery_note_id')) {
            Schema::table('taxes', function (Blueprint $table) {
                $table->dropIndex('taxes_delivery_note_idx');
                $table->dropForeign(['delivery_note_id']);
                $table->dropColumn('delivery_note_id');
            });
        }
    }
}
