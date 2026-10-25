<?php

namespace App\Enums;

enum OutletRange: string
{
    case One = '1';
    case TwoToFive = '2-5';
    case SixToFifteen = '6-15';
    case SixteenToFifty = '16-50';
    case OverFifty = '50+';
}
