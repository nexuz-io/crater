<?php

namespace Crater\Http\Controllers\V1\Admin\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\DeliveryNoteRequest;
use Crater\Http\Resources\DeliveryNoteResource;
use Crater\Models\DeliveryNote;
use Illuminate\Http\Request;

/**
 * Plan 09 §F11 + plan 05 — CRUD basico de DeliveryNote.
 *
 * El alcance de F11 incluye un endpoint POST para crear + actualizar
 * albaranes con validacion. La transformacion a Invoice se delega
 * al `ConvertDeliveryNoteController` que usa el converter de F11.
 */
class DeliveryNotesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = (int) $request->header('company');

        return DeliveryNoteResource::collection(
            DeliveryNote::where('company_id', $companyId)
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

        return new DeliveryNoteResource(
            $deliveryNote->load('items', 'taxes')
        );
    }

    public function update(DeliveryNoteRequest $request, DeliveryNote $delivery_note)
    {
        $this->authorize('update', $delivery_note);

        $delivery_note->update($request->validated());

        return new DeliveryNoteResource(
            $delivery_note->fresh()->load('items', 'taxes')
        );
    }

    public function destroy(Request $request, DeliveryNote $delivery_note)
    {
        $this->authorize('delete', $delivery_note);
        $delivery_note->delete();

        return response()->json(['success' => true]);
    }
}
