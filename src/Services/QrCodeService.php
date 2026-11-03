<?php
declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class QrCodeService
{
    public static function buildUpiUri(
        string $upiId,
        string $payeeName,
        float $amount,
        string $transactionNote = 'Milk Bill Payment'
    ): string {
        $encodedName = rawurlencode($payeeName);
        $encodedNote = rawurlencode($transactionNote);
        $amountFormatted = number_format($amount, 2, '.', '');

        return "upi://pay?pa={$upiId}&pn={$encodedName}&am={$amountFormatted}&cu=INR&tn={$encodedNote}";
    }

    public static function renderQrTag(
        ?string $customQrPath,
        ?string $upiId,
        string $payeeName,
        float $amount,
        int $size = 140,
        string $note = 'Milk Bill Payment'
    ): string {
        // 1. If custom QR image was uploaded by milkman, display it
        if (!empty($customQrPath)) {
            $webPath = asset('public/' . ltrim($customQrPath, '/'));
            return "<img src='{$webPath}' alt='UPI QR Code' class='border border-slate-300 rounded-lg p-1 bg-white inline-block object-contain' style='width:{$size}px; height:{$size}px;' />";
        }

        // 2. Generate native offline SVG/Base64 QR code from UPI URI
        if (!empty($upiId)) {
            $upiUri = self::buildUpiUri($upiId, $payeeName, $amount, $note);

            try {
                $options = new QROptions();
                $options->version = 5;
                $options->outputBase64 = true;
                $options->scale = 4;
                $options->imageTransparent = false;

                $qrImageSrc = (new QRCode($options))->render($upiUri);
                return "<img src='{$qrImageSrc}' alt='UPI Payment QR' class='border border-slate-300 rounded-lg p-1 bg-white inline-block' style='width:{$size}px; height:{$size}px;' />";
            } catch (\Throwable $e) {
                // Fallback to online API if needed
                $encodedData = urlencode($upiUri);
                $fallbackUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}&margin=1";
                return "<img src='{$fallbackUrl}' alt='UPI Payment QR' class='border border-slate-300 rounded-lg p-1 bg-white inline-block' style='width:{$size}px; height:{$size}px;' />";
            }
        }

        return "<div class='text-xs text-slate-400 p-3 border border-dashed rounded text-center'>No UPI QR Configured</div>";
    }
}
