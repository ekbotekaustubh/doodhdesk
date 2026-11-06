<?php
declare(strict_types=1);

namespace App\Enums;

enum ChargeType: string
{
    case FlatMonthly = 'flat_monthly';
    case PerLiter = 'per_liter';
    case PerDeliveryDay = 'per_delivery_day';

    public function label(): string
    {
        return match($this) {
            self::FlatMonthly => 'Flat Monthly (Per Month)',
            self::PerLiter => 'Per Liter Delivered (₹/L)',
            self::PerDeliveryDay => 'Per Delivery Day (₹/Day)',
        };
    }

    public function shortLabel(): string
    {
        return match($this) {
            self::FlatMonthly => 'Flat / Month',
            self::PerLiter => '/ Liter',
            self::PerDeliveryDay => '/ Delivery Day',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::FlatMonthly => 'Fixed monthly amount charged on the customer invoice.',
            self::PerLiter => 'Calculated as: Amount × Total Delivered Liters in the billing cycle.',
            self::PerDeliveryDay => 'Calculated as: Amount × Total Days Delivered (excluding skipped and leave days).',
        };
    }
}
