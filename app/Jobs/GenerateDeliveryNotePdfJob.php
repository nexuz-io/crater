<?php

namespace Crater\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * GenerateDeliveryNotePdfJob — gemelo de GenerateEstimatePdfJob.
 *
 * Plan 05 §6 + plan 09 PR-11 / F11: el PDF del albarán se
 * despacha como trabajo de cola exclusivamente DESPUÉS del commit
 * del documento. Reutiliza `GeneratesPdfTrait::generatePDF` que
 * `DeliveryNote` adopta vía `use GeneratesPdfTrait`.
 */
class GenerateDeliveryNotePdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deliveryNote;

    public $deleteExistingFile;

    public function __construct($deliveryNote, $deleteExistingFile = false)
    {
        $this->deliveryNote = $deliveryNote;
        $this->deleteExistingFile = $deleteExistingFile;
    }

    public function handle()
    {
        $this->deliveryNote->generatePDF(
            'delivery_note',
            $this->deliveryNote->delivery_number,
            $this->deleteExistingFile
        );

        return 0;
    }
}
