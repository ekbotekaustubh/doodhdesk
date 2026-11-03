<?php
declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Upi = 'upi';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return match($this) {
            self::Upi => 'UPI / QR Code',
            self::Cash => 'Cash in Hand',
            self::BankTransfer => 'Bank Transfer / IMPS / NEFT',
            self::Cheque => 'Cheque',
            self::Other => 'Other',
        };
    }
}
