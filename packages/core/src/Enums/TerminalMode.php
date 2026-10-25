<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

enum TerminalMode: string
{
    case TableService = 'table_service';
    case Tabs = 'tabs';
    case Appointments = 'appointments';
    case QuickSale = 'quick_sale';
    case MultiLane = 'multi_lane';
}
