<?php
declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Partial = 'partial';
    case Free = 'free';
    case Active = 'active';
    case Expired = 'expired';

    public function label(): string
    {
        return match($this) {
            self::Unpaid => 'Unpaid',
            self::Paid => 'Paid in Full',
            self::Partial => 'Partially Paid',
            self::Free => 'Free Tier',
            self::Active => 'Active Paid',
            self::Expired => 'Subscription Expired',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Unpaid => 'bg-amber-100 text-amber-800 border-amber-300',
            self::Paid => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            self::Partial => 'bg-sky-100 text-sky-800 border-sky-300',
            self::Free => 'bg-blue-100 text-blue-800 border-blue-300',
            self::Active => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            self::Expired => 'bg-rose-100 text-rose-800 border-rose-300',
        };
    }
}
