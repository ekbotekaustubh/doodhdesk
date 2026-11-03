<?php
declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfExportService
{
    public static function streamPdf(string $html, string $filename, string $paper = 'A4', string $orientation = 'portrait'): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);

        if ($paper === 'thermal80') {
            // 80mm width = ~226.77 points, height 800pt
            $dompdf->setPaper([0, 0, 226.77, 850], 'portrait');
        } elseif ($paper === 'thermal58') {
            // 58mm width = ~164.4 points, height 800pt
            $dompdf->setPaper([0, 0, 164.4, 850], 'portrait');
        } else {
            $dompdf->setPaper($paper, $orientation);
        }

        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => false]);
        exit;
    }
}
