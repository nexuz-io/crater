<?php

namespace Crater\Http\Controllers\V1\PDF;

use Crater\Http\Controllers\Controller;
use Crater\Models\DeliveryNote;
use Illuminate\Http\Request;

/**
 * DeliveryNotePdfController — público con `unique_hash` (plan 05 §7).
 *
 * Ruta: `/delivery-notes/pdf/{delivery_note:unique_hash}` (ver
 * routes/web.php; `pdf-auth` middleware garantiza autenticación).
 *
 * Coherencia con InvoicePdfController / EstimatePdfController:
 * - `?preview=1` devuelve el PDF inline (sin descarga en disco).
 * - sin param devuelve PDF cacheado en media collection o stream.
 */
class DeliveryNotePdfController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $deliveryNote)
    {
        if ($request->has('preview')) {
            return $deliveryNote->getPDFData();
        }

        return $deliveryNote->getGeneratedPDFOrStream('delivery_note');
    }
}
