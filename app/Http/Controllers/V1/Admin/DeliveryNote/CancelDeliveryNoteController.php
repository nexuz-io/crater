<?php

namespace Crater\Http\Controllers\V1\Admin\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Crater\Models\DeliveryNote;
use Illuminate\Http\Request;

class CancelDeliveryNoteController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $delivery_note)
    {
        $this->authorize('update', $delivery_note);

        try {
            $delivery_note->cancel();
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 422);
        }

        return $delivery_note->fresh();
    }
}
