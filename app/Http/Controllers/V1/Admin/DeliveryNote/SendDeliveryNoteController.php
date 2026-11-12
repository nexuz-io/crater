<?php

namespace Crater\Http\Controllers\V1\Admin\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\SendDeliveryNoteRequest;
use Crater\Jobs\GenerateDeliveryNotePdfJob;
use Crater\Models\DeliveryNote;

/**
 * Plan 05 §6 + plan 09 PR-11 — envío real del DeliveryNote por email.
 *
 * Gemelo de `SendEstimateController`:
 *  - Valida con `SendDeliveryNoteRequest` (subject/body/from/to).
 *  - Delega a `DeliveryNote::send()` que crea EmailLog + SendDeliveryNoteMail.
 *  - No fuerza la transición a DELIVERED (puede enviarse desde DRAFT
 *    o DELIVERED; la transición es independiente, vía
 *    `MarkAsDeliveredController`).
 *  - Despacha `GenerateDeliveryNotePdfJob` para refrescar la cache
 *    del PDF tras la posible mutación del estado.
 */
class SendDeliveryNoteController extends Controller
{
    public function __invoke(SendDeliveryNoteRequest $request, DeliveryNote $delivery_note)
    {
        $this->authorize('send', $delivery_note);

        $response = $delivery_note->send($request->all());

        GenerateDeliveryNotePdfJob::dispatch($delivery_note, true);

        return response()->json($response);
    }
}
