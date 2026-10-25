<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

enum PaymentType: string
{
    case Cash = 'cash';
    case Mpesa = 'mpesa';
    case Card = 'card';
    case AirtelMoney = 'airtel_money';
    case Split = 'split';
}
