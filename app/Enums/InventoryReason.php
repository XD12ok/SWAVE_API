<?php

namespace App\Enums;

enum InventoryReason: string
{
    case ORDER = 'ORDER';
    case RESTOCK = 'RESTOCK';
    case MANUAL = 'MANUAL';
    case EXPIRED = 'EXPIRED';
    case ADJUSTMENT = 'ADJUSTMENT';
}
