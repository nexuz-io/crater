<?php

namespace Crater\Http\Controllers\V1\Admin\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Crater\Models\DeliveryNote;
use Illuminate\Http\Request;

/**
 * Plan 09 §F11 + plan 05 — Lista de templates de albaranes
 * disponibles escaneando `resources/views/app/pdf/delivery-note/*.blade.php`.
 * Gemelo de la convención usada en Estimate/Invoice.
 */
class DeliveryNoteTemplatesController extends Controller
{
    public function __invoke(Request $request)
    {
        return [
            'deliveryNoteTemplates' => DeliveryNote::deliveryNoteTemplates(),
        ];
    }
}
