<?php

namespace Crater\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * DeliveryNoteViewedMail — gemelo de InvoiceViewedMail /
 * EstimateViewedMail. Plan 05 §6.
 *
 * Se envía cuando el cliente abre el PDF público del albarán
 * (token vía `EmailLog`). Estado de apertura registrado en
 * `email_log.viewed_at` (gestionado fuera de este plan).
 */
class DeliveryNoteViewedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $data = [];

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->from($this->data['from'], config('mail.from.name'))
            ->subject($this->data['subject'])
            ->markdown('emails.viewed.delivery-note', ['data', $this->data]);
    }
}
