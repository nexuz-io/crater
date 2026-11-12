<?php

namespace Crater\Http\Controllers\V1\Admin\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Crater\Models\DeliveryNote;
use Illuminate\Http\Request;

/**
 * Plan 09 §F11 + plan 05 — convertidor de DeliveryNote a Invoice.
 *
 * Delega en `DeliveryNote::billToInvoice()` que a su vez invoca el
 * `DeliveryNoteToInvoiceConverter` registrado en `DocumentConverterRegistry`.
 * La autorización usa `convert-delivery-note-invoice` (ability nuevo).
 */
class ConvertDeliveryNoteController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $delivery_note)
    {
        $this->authorize('convertToInvoice', $delivery_note);

        $invoice = $delivery_note->billToInvoice();

        return response()->json([
            'success' => true,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ],
        ]);
    }
}
