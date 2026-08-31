<?php

namespace App\Enums;

enum ShippingMethod: string
{
    case PICKUP = 'PICKUP';
    case DELIVERY = 'DELIVERY';
}
