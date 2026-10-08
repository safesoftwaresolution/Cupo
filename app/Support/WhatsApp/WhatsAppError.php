<?php

namespace App\Support\WhatsApp;

use RuntimeException;

class WhatsAppError extends RuntimeException
{
    public function __construct(string $message, public readonly bool $reintentable = false)
    {
        parent::__construct($message);
    }
}
