<?php

namespace Crater\Http\Controllers\V1\Admin\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\DeleteDeliveryNotesRequest;
use Crater\Http\Requests\DeliveryNoteRequest;
use Crater\Http\Resources\DeliveryNoteResource;
use Crater\Jobs\GenerateDeliveryNotePdfJob;
use Crater\Models\DeliveryNote;
use Illuminate\Http\Request;

/**
 * Plan 09 §F11 + plan 05 — CRUD basico de DeliveryNote.
 *
 * El alcance de F11 incluye un endpoint POST para crear + actualizar
 * albaranes con validacion. La transformacion a Invoice se delega
 * al `ConvertDeliveryNoteController` que usa el converter de F11.
 *
 * F11a — añade:
 *  - Despacho de `GenerateDeliveryNotePdfJob` tras `store`/`update`
 *    para mantener el PDF cacheado coherente.
 *  - `delete(DeleteDeliveryNotesRequest)` para borrado bulk.
 */
class DeliveryNotesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = (int) $request->header('company');

        // Eager-load customer: el Index.vue del admin lee
        // `row.data.customer.name` y `row.data.customer.currency`
        // (cell-name y cell-total). Sin eager-load esto haría N+1
        // queries en listados de 20+ items. La relación ya existe
        // en la migration (FK customer_id) y el modelo declara
        // belongsTo; with('customer') la pre-resuelve.
        //
        // No podemos eager-load `customer.items` porque la relación
        // `customer` es BelongsTo (no soporta anidar HasMany). Si
        // algún día necesitamos los items en el listado, mejor
        // cargar también `with('items')` aquí, o dejar al Resource
        // que los pida bajo `whenLoaded('items')` (que es lo que
        // tenemos hoy).
        return DeliveryNoteResource::collection(
            DeliveryNote::with('customer')
                ->where('company_id', $companyId)
                ->orderByDesc('delivery_date')
                ->paginate(20)
        );
    }

    public function show(Request $request, DeliveryNote $delivery_note)
    {
        $this->authorize('view', $delivery_note);

        return new DeliveryNoteResource(
            $delivery_note->load('items', 'taxes', 'customer')
        );
    }

    public function store(DeliveryNoteRequest $request)
    {
        $this->authorize('create', DeliveryNote::class);

        $payload = $request->validated();
        $payload['creator_id'] = $request->user()->id;
        $payload['company_id'] = (int) $request->header('company');
        $payload['status'] = $payload['status'] ?? DeliveryNote::STATUS_DRAFT;

        $deliveryNote = DeliveryNote::create($payload);

        // Items + taxes
        if (! empty($request->items)) {
            foreach ($request->items as $line) {
                $line['company_id'] = $deliveryNote->company_id;
                $deliveryNote->items()->create($line);
            }
        }
        if (! empty($request->taxes)) {
            foreach ($request->taxes as $tax) {
                $tax['delivery_note_id'] = $deliveryNote->id;
                $deliveryNote->taxes()->create($tax);
            }
        }

        // Despacha la generación del PDF tras commit.
        GenerateDeliveryNotePdfJob::dispatch($deliveryNote);

        return new DeliveryNoteResource(
            $deliveryNote->load('items', 'taxes')
        );
    }

    public function update(DeliveryNoteRequest $request, DeliveryNote $delivery_note)
    {
        $this->authorize('update', $delivery_note);

        $delivery_note->update($request->validated());

        // Regenera el PDF (sobrescribe cache anterior).
        GenerateDeliveryNotePdfJob::dispatch($delivery_note, true);

        return new DeliveryNoteResource(
            $delivery_note->fresh()->load('items', 'taxes')
        );
    }

    public function destroy(Request $request, DeliveryNote $delivery_note)
    {
        $this->authorize('delete', $delivery_note);

        // Plan 05 §3: albarán ya facturado NO se borra físicamente.
        if ($delivery_note->alreadyBilled()) {
            return response()->json([
                'error' => 'cannot_delete_billed',
                'message' => __('delivery-notes.cannot_delete_billed'),
            ], 422);
        }

        $delivery_note->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Bulk delete — gemelo de EstimatesController::delete.
     * Sólo aplica `delete()` a albaranes no facturados (los ya
     * facturados quedan intactos por trazabilidad contable).
     */
    public function delete(DeleteDeliveryNotesRequest $request)
    {
        $this->authorize('deleteMultiple', DeliveryNote::class);

        DeliveryNote::deleteDeliveryNotes($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
