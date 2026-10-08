<?php

namespace App\Enums;

enum OrigenCita: string
{
    case Manual = 'manual';
    case Web = 'web';
    case Whatsapp = 'whatsapp';
}
