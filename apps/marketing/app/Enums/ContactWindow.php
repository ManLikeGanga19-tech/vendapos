<?php

namespace App\Enums;

/** Preferred call-back time, in East Africa Time. */
enum ContactWindow: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
}
