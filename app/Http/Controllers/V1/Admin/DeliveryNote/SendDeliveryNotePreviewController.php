<?php

namespace Crater\Http\Controllers\V1\Admin\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Crater\Models\DeliveryNote;
use Illuminate\Http\Request;

class SendDeliveryNotePreviewController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $delivery_note)
    {
        $this->authorize('send', $delivery_note);

        return $delivery_note;
    }
}
