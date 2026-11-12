<?php

namespace Crater\Http\Controllers\V1\Customer\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\Customer\DeliveryNoteResource;
use Crater\Models\Company;
use Crater\Models\DeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Plan 05 §8 + plan 09 §F11 — portal de cliente: vista index/show
 * de los DeliveryNote del cliente autenticado.
 *
 * El cliente no tiene acceso a borradores: mientras el albarán esté
 * en DRAFT (no entregado), la entrada no aparece en el portal —
 * coherente con la convención ya aplicada en Invoices y Estimates.
 * Cuando el albarán entra en DELIVERED o CANCELLED ya es visible.
 */
class DeliveryNotesController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;

        $deliveryNotes = DeliveryNote::with(['items', 'customer', 'taxes'])
            ->where('status', '<>', DeliveryNote::STATUS_DRAFT)
            ->whereCustomer(Auth::guard('customer')->id())
            ->applyFilters($request->only([
                'status',
                'delivery_number',
                'from_date',
                'to_date',
                'orderByField',
                'orderBy',
            ]))
            ->latest('delivery_date')
            ->paginateData($limit);

        return (DeliveryNoteResource::collection($deliveryNotes))
            ->additional(['meta' => [
                'deliveryNoteTotalCount' => DeliveryNote::where('status', '<>', DeliveryNote::STATUS_DRAFT)
                    ->whereCustomer(Auth::guard('customer')->id())
                    ->count(),
            ]]);
    }

    public function show(Company $company, $id)
    {
        $deliveryNote = $company->deliveryNotes()
            ->whereCustomer(Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();

        if (! $deliveryNote) {
            return response()->json(['error' => 'delivery_note_not_found'], 404);
        }

        return new DeliveryNoteResource($deliveryNote);
    }
}
