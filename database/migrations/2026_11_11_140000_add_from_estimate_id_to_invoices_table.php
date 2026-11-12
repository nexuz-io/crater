<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 09 §F5 — Variante A: FK explícita `invoices.from_estimate_id`.
 *
 * Añade la columna opcional `from_estimate_id` que referencia a
 * `estimates.id`. Su función es trazabilidad de origen: cada Invoice
 * convertida desde un Estimate registra explícitamente el id de la
 * Estimate de origen (incluso si el flujo de conversión las desvincula
 * luego en el ciclo via `Estimate::checkForEstimateConvertAction`).
 *
 * Decisiones:
 *
 * - **Nullable UNIQUE NO**: una misma Estimate puede dar lugar a
 *   varias Invoices en escenarios override (cliente duplica la
 *   conversión, override manual, etc.). Por eso NO hay índice único.
 *
 * - **`onDelete('set null')`**: si se borra la Estimate de origen,
 *   la columna queda NULL pero las Invoices derivadas sobreviven.
 *   Esto preserva el historial bajo la doctrina HR4 (snapshot del
 *   origen) y HR7 (atomicidad no se ve comprometida por deletes
 *   posteriores).
 *
 * - **Index compuesto `[company_id, from_estimate_id]`**: optimiza las
 *   consultas que filtran por empresa y origen (ej. `Estimate::invoicesFromEstimate`
 *   con scope por empresa). El índice simple `from_estimate_id` solo
 *   sería suficiente si no hubiera multi-tenant, pero el compuesto evita
 *   exploración de tabla cuando también filtras por `company_id`.
 *
 * Reversibilidad: el `down()` borra FK, índice y columna. Los datos
 * históricos quedan con el valor NULL pre-existente (safe).
 */
class AddFromEstimateIdToInvoicesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'from_estimate_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedInteger('from_estimate_id')->nullable();
                $table->foreign('from_estimate_id')
                    ->references('id')->on('estimates')
                    ->onDelete('set null');
                $table->index(['company_id', 'from_estimate_id'], 'invoices_company_from_estimate_idx');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'from_estimate_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['from_estimate_id']);
                $table->dropIndex('invoices_company_from_estimate_idx');
                $table->dropColumn('from_estimate_id');
            });
        }
    }
}
