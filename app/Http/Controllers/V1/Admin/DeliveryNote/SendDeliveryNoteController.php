<?php

namespace Crater\Http\Controllers\V1\Admin\DeliveryNote;

use Crater\Http\Controllers\Controller;
use Crater\Models\CompanySetting;
use Crater\Models\DeliveryNote;
use Crater\Models\User;
use Illuminate\Http\Request;

class SendDeliveryNoteController extends Controller
{
    public function __invoke(Request $request, DeliveryNote $delivery_note, User $user)
    {
        $this->authorize('send', $delivery_note);

        $data = $request->all();
        $body = CompanySetting::getSetting('delivery_note_mail_body', $delivery_note->company_id);

        return $delivery_note; // shell — fuera del scope de F11
    }
}
