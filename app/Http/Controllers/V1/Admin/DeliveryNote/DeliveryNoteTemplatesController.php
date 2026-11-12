<?php

namespace Crater\Http\Controllers\V1\Admin\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Plan 09 §F11 — Lista de templates de albaranes disponibles.
 *
 * Placeholder: el listado real lee las vistas `resources/views/app/pdf/delivery_note/*.blade.php`.
 * F11 entra con shell porque la visualizacion del PDF no esta en alcance.
 */
class DeliveryNoteTemplatesController extends Controller
{
    public function __invoke(Request $request)
    {
        return [
            ['name' => 'delivery_note1', 'view' => 'app.pdf.delivery_note.delivery_note1'],
        ];
    }
}
