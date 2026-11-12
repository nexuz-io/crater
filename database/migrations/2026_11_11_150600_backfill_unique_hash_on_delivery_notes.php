<?php

use Illuminate\Database\Migrations\Migration;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Backfill `unique_hash` para los `delivery_notes` creados antes
 * de que el modelo tuviera el `created` listener.
 *
 * Matchea el patrón de `Version300.php::up()` para Payment:
 * itera cada fila con `unique_hash` NULL y le asigna
 * `Hashids::connection(DeliveryNote::class)->encode($id)`. La
 * asignación es idempotente (el filtro WHERE ya excluye las filas
 * que tenían valor correcto, y encode() es determinista), por lo
 * re-ejecuciones seguras.
 *
 * Sin este campo el endpoint `GET /delivery-notes/pdf/{hash}` resuelve
 * a 404 (route-model-binding por `unique_hash`) y la SPA muestra el
 * iframe sin `src` — manifestándose como "no se carga el preview del
 * PDF" en el listado admin.
 *
 * `down()` deja el campo como estaba antes: nada que revertir.
 */
class BackfillUniqueHashOnDeliveryNotes extends Migration
{
    public function up()
    {
        $rows = \Illuminate\Support\Facades\DB::table('delivery_notes')
            ->whereNull('unique_hash')
            ->orderBy('id')
            ->cursor();

        foreach ($rows as $row) {
            \Illuminate\Support\Facades\DB::table('delivery_notes')
                ->where('id', $row->id)
                ->update([
                    'unique_hash' => Hashids::connection(\Crater\Models\DeliveryNote::class)
                        ->encode($row->id),
                ]);
        }
    }

    public function down()
    {
        // Intencionalmente vacío: revertir podría colisionar con
        // enlaces públicos ya emitidos a clientes.
    }
}
