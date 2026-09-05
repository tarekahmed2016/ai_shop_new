<?php

namespace App\Enums\CustomerRequests;

enum IntakeAction: string
{
    case Classify = 'classify';
    case Retry = 'retry';
    case Confirm = 'confirm';
}
