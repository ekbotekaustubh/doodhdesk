<?php
declare(strict_types=1);

namespace App\Enums;

enum DeliveryShift: string
{
    case Morning = 'morning';
    case Evening = 'evening';

    public function label(): string
    {
        return match($this) {
            self::Morning => 'Morning Shift',
            self::Evening => 'Evening Shift',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Morning => '🌅',
            self::Evening => '🌆',
        };
    }
}
