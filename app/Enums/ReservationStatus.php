<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case ACTIVE = 'ACTIVE';
    case RELEASED = 'RELEASED';
    case CONSUMED = 'CONSUMED';
    case EXPIRED = 'EXPIRED';
}
