<?php
declare(strict_types=1);

namespace App\Enums;

enum MilkType: string
{
    case Cow = 'cow';
    case Buffalo = 'buffalo';

    public function label(): string
    {
        return match($this) {
            self::Cow => 'Cow Milk',
            self::Buffalo => 'Buffalo Milk',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Cow => '🐄',
            self::Buffalo => '🐃',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Cow => 'bg-amber-100 text-amber-800 border-amber-300',
            self::Buffalo => 'bg-indigo-100 text-indigo-800 border-indigo-300',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Cow->value => self::Cow->label(),
            self::Buffalo->value => self::Buffalo->label(),
        ];
    }
}
