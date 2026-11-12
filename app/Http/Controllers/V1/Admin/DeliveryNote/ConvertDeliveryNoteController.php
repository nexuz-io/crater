<?php

namespace Crater\Http\Controllers\V1\Admin\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\InvoiceResource;
use Crater\Models\DeliveryNote;
use Crater\Services\Documents\DocumentConverterRegistry;
use Illuminate\Http\Request;

/**
 * Plan 09 §F11 + plan 05 — convertidor de DeliveryNote a Invoice.
 *
 * Delega en el `DeliveryNoteToInvoiceConverter` registrado en
 * `DocumentConverterRegistry` y devuelve un `InvoiceResource`
 * completo (mismo shape que cuando se crea una Invoice desde el
 * flujo normal) para que el frontend pueda navegar al editor
 * con `router.push({ name: 'invoices.edit', params: { id } })`.
 *
 * La conversión es idempotente gracias a la FK UNIQUE
 * `invoices.delivery_note_id` + el chequeo `lockForUpdate` del
 * converter: si ya existe la Invoice vinculada, se devuelve esa.
 */
class ConvertDeliveryNoteController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $delivery_note, DocumentConverterRegistry $registry)
    {
        $this->authorize('convertToInvoice', $delivery_note);

        $converter = $registry->get('delivery-note', 'invoice');

        if (! $converter) {
            abort(500, 'No DocumentConverter registered for delivery-note->invoice');
        }

        $invoice = $converter->convert($delivery_note);

        return new InvoiceResource($invoice);
    }
}
