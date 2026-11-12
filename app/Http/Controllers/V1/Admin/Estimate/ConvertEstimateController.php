<?php

namespace Crater\Http\Controllers\V1\Admin\Estimate;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\InvoiceResource;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Services\Documents\DocumentConverterRegistry;
use Illuminate\Http\Request;

/**
 * Plan 09 §F3 — endpoint POST /estimates/{estimate}/convert-to-invoice.
 *
 * Delega la conversión al `EstimateToInvoiceConverter` registrado en
 * `DocumentConverterRegistry`. Mantiene la autorización actual
 * `create` sobre Invoice (la separación por ability `convert-estimate-invoice`
 * entra con F6).
 */
class ConvertEstimateController extends Controller
{
    public function __invoke(Request $request, Estimate $estimate, DocumentConverterRegistry $registry)
    {
        // Plan 09 §F6 (D7): separamos la autorización de la conversión de
        // la autorización de crear invoices. Roles con `create-invoice` NO
        // heredan automáticamente la conversión; se requiere el ability
        // opt-in `convert-estimate-invoice`.
        $this->authorize('convert-estimate-invoice', $estimate);

        $converter = $registry->get('estimate', 'invoice');

        if (! $converter) {
            abort(500, 'No DocumentConverter registered for estimate->invoice');
        }

        $invoice = $converter->convert($estimate);

        return new InvoiceResource($invoice);
    }
}
