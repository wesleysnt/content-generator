<?php

namespace App\Enums;

enum VariationStatus: string
{
    case Pending = 'pending';
    case Generated = 'generated';
    case Discarded = 'discarded';
    case Final = 'final';
}
