<?php

namespace Crater\Http\Controllers\V1\Customer;

use Crater\Http\Controllers\Controller;
use Crater\Models\DeliveryNote;
use Illuminate\Http\Request;

/**
 * DeliveryNotePdfController — portal de cliente.
 *
 * Rutas definidas en `routes/web.php`:
 *
 *   GET /delivery-notes/{email_log:token}              -> getDeliveryNote
 *   GET /delivery-notes/view/{email_log:token}  named: delivery-note
 *
 * El middleware `pdf-auth` autoriza ambas rutas (mismas reglas
 * que los PDFs públicos de Invoice/Estimate).
 */
class DeliveryNotePdfController extends Controller
{
    /**
     * Pre-render: devuelve el objeto del albarán para que la
     * vista Blade (no PDF) pueda mostrar resumen. Usado por la
     * landing page del portal de cliente.
     *
     * En el plan 05 §7 sólo necesitamos preparar datos; la
     * página pública del cliente termina resolviendo a `?pdf=1`
     * para entregar el PDF stream.
     */
    public function getDeliveryNote(Request $request, string $email_log)
    {
        $deliveryNote = DeliveryNote::where('unique_hash', $email_log)->firstOrFail();

        return view('app.pdf.delivery-note.delivery-note-1', [
            'deliveryNote' => $deliveryNote->load(['customer', 'items']),
        ]);
    }

    /**
     * Stream del PDF (`?pdf=1`). Cuando el cliente abre la URL
     * `route('delivery-note', $token).'?pdf=1'`, queremos
     * entregar directamente el PDF para descarga o previsualización.
     */
    public function getPdf(Request $request, DeliveryNote $deliveryNote)
    {
        return $deliveryNote->getGeneratedPDFOrStream('delivery_note');
    }
}
