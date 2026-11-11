<?php

namespace Crater\Poc\Documents\Contracts;

interface Mailable
{
    /** Envía el documento al cliente. Devuelve un array de respuesta. */
    public function send(array $data): array;

    /** Previsualiza el envío (sin enviar). */
    public function preview(array $data): array;
}
