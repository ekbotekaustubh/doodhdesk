<?php
declare(strict_types=1);

namespace App\Enums;

enum DeliveryStatus: string
{
    case Delivered = 'delivered';
    case Skipped = 'skipped';
    case Leave = 'leave';

    public function label(): string
    {
        return match($this) {
            self::Delivered => 'Delivered',
            self::Skipped => 'Missed / Skipped',
            self::Leave => 'Customer On Leave',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Delivered => 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-700',
            self::Skipped => 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950 dark:text-rose-300 dark:border-rose-700',
            self::Leave => 'bg-slate-200 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Delivered => '✓',
            self::Skipped => '✗',
            self::Leave => '🏖',
        };
    }
}
