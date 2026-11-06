<?php
declare(strict_types=1);

// Global Helper Functions for DairyFlow SaaS

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatMoney(float|int|string $amount, string $symbol = '₹'): string
{
    return $symbol . number_format((float)$amount, 2);
}

function formatDate(?string $date, string $format = 'd M Y'): string
{
    if (empty($date)) {
        return '-';
    }
    return (new \DateTime($date))->format($format);
}

function url(string $path = '/'): string
{
    static $base = null;
    if ($base === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (str_ends_with($scriptName, 'index.php')) {
            $base = $scriptName;
        } else {
            $dir = str_replace('\\', '/', dirname($scriptName));
            $base = ($dir === '/' || $dir === '.') ? '/index.php' : rtrim($dir, '/') . '/index.php';
        }
    }
    $cleanPath = '/' . ltrim($path, '/');
    if ($cleanPath === '/') {
        return $base;
    }
    return $base . $cleanPath;
}

function asset(string $path): string
{
    static $baseDir = null;
    if ($baseDir === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($scriptName));
        $baseDir = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }
    return $baseDir . '/' . ltrim($path, '/');
}

function isActiveRoute(string $prefix): bool
{
    $req = new \App\Core\Request();
    $current = $req->path();
    if ($prefix === '/' && $current === '/') {
        return true;
    }
    return $prefix !== '/' && str_starts_with($current, $prefix);
}

/**
 * @return string[]
 */
function parseMilkTypes(?string $types): array
{
    if (empty($types)) {
        return ['cow'];
    }
    $items = array_values(array_filter(array_map('trim', explode(',', $types))));
    return !empty($items) ? $items : ['cow'];
}

function formatMilkTypesBadge(?string $types): string
{
    $list = parseMilkTypes($types);
    $badges = [];
    foreach ($list as $t) {
        if ($t === 'cow') {
            $badges[] = '<span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full font-bold bg-amber-50 text-amber-900 border border-amber-300">🐄 Cow</span>';
        } elseif ($t === 'buffalo') {
            $badges[] = '<span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full font-bold bg-indigo-50 text-indigo-900 border border-indigo-300">🐃 Buffalo</span>';
        } else {
            $badges[] = '<span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full font-bold bg-slate-100 text-slate-800 border border-slate-300">' . e(ucfirst($t)) . '</span>';
        }
    }
    return implode(' ', $badges);
}

function formatMilkTypeText(?string $types): string
{
    $list = parseMilkTypes($types);
    if (empty($list)) {
        return 'Cow Milk';
    }
    $labels = [];
    foreach ($list as $t) {
        if ($t === 'cow') {
            $labels[] = 'Cow Milk';
        } elseif ($t === 'buffalo') {
            $labels[] = 'Buffalo Milk';
        } else {
            $labels[] = ucfirst($t) . ' Milk';
        }
    }
    return implode(' & ', $labels);
}

/**
 * Builds a 1-to-N calendar matrix of days for an invoice, marking each date with its milk quantity and status.
 *
 * @param array $breakdown The invoice breakdown data (from breakdown_json or BillingService)
 * @param int $year Billing year
 * @param int $month Billing month
 * @return array{matrix: array<int, array>, first_day_of_week: int, days_in_month: int}
 */
function buildCalendarMatrix(array $breakdown, int $year, int $month): array
{
    if (!empty($breakdown['calendar_matrix']) && !empty($breakdown['first_day_of_week'])) {
        return [
            'matrix' => $breakdown['calendar_matrix'],
            'first_day_of_week' => (int)$breakdown['first_day_of_week'],
            'days_in_month' => (int)($breakdown['days_in_month'] ?? count($breakdown['calendar_matrix'])),
        ];
    }

    $daysInMonth = (int)($breakdown['days_in_month'] ?? cal_days_in_month(CAL_GREGORIAN, $month, $year));
    $firstDayOfMonth = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
    $firstDayOfWeek = (int)$firstDayOfMonth->format('N'); // 1 = Mon, 7 = Sun

    // Index existing itemized deliveries by date
    $itemized = $breakdown['itemized_deliveries'] ?? [];
    $byDate = [];
    foreach ($itemized as $it) {
        $dStr = $it['date'] ?? '';
        if (!empty($dStr)) {
            if (!isset($byDate[$dStr])) {
                $byDate[$dStr] = [];
            }
            $byDate[$dStr][] = $it;
        }
    }

    $matrix = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $curDate = new \DateTime($dStr);
        $dayOfWeek = (int)$curDate->format('N');
        $dayDeliveries = $byDate[$dStr] ?? [];

        $dayQty = 0.0;
        $dayStatus = 'unrecorded';
        $notes = [];
        $shifts = [];

        if (!empty($dayDeliveries)) {
            $hasDelivered = false;
            $hasSkipped = false;
            $hasLeave = false;

            foreach ($dayDeliveries as $item) {
                $st = $item['status'] ?? '';
                $q = (float)($item['quantity'] ?? 0);
                $shift = $item['shift'] ?? 'morning';
                $shifts[$shift] = ['status' => $st, 'quantity' => $q];

                if (!empty($item['notes'])) {
                    $notes[] = $item['notes'];
                }

                if ($st === 'delivered') {
                    $hasDelivered = true;
                    $dayQty += $q;
                } elseif ($st === 'skipped') {
                    $hasSkipped = true;
                } elseif ($st === 'leave') {
                    $hasLeave = true;
                }
            }

            if ($hasDelivered) {
                $dayStatus = 'delivered';
            } elseif ($hasSkipped) {
                $dayStatus = 'skipped';
                $dayQty = 0.0;
            } elseif ($hasLeave) {
                $dayStatus = 'leave';
                $dayQty = 0.0;
            }
        }

        $matrix[$d] = [
            'day' => $d,
            'date' => $dStr,
            'day_of_week' => $dayOfWeek,
            'day_name' => $curDate->format('D'),
            'is_weekend' => $dayOfWeek >= 6,
            'quantity' => round($dayQty, 2),
            'status' => $dayStatus, // 'delivered', 'skipped', 'leave', 'unrecorded'
            'shifts' => $shifts,
            'notes' => implode(', ', $notes),
        ];
    }

    return [
        'matrix' => $matrix,
        'first_day_of_week' => $firstDayOfWeek,
        'days_in_month' => $daysInMonth,
    ];
}
