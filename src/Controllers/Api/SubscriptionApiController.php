<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Services\QuotaService;

final class SubscriptionApiController extends ApiBaseController
{
    private QuotaService $quotaService;

    public function __construct()
    {
        parent::__construct();
        $this->quotaService = new QuotaService();
    }

    public function getSubscription(Request $request): void
    {
        $tenantId = $this->tenantId();
        $quota = $this->quotaService->getTenantQuota($tenantId);
        $settings = $this->quotaService->getPlatformSettings();

        $this->jsonSuccess([
            'quota' => $quota,
            'pricing' => [
                'free_customer_limit' => $settings['free_customer_limit'],
                'paid_tier_unit_size' => $settings['paid_tier_unit_size'],
                'paid_tier_unit_price' => $settings['paid_tier_unit_price'],
                'currency_symbol' => $settings['currency_symbol'],
            ]
        ]);
    }

    public function upgradeQuota(Request $request): void
    {
        $tenantId = $this->tenantId();
        $blocks = max(1, (int)$request->input('blocks', 1));

        $freshQuota = $this->quotaService->upgradeCapacity($tenantId, $blocks);

        $settings = $this->quotaService->getPlatformSettings();
        $unitPrice = (float)$settings['paid_tier_unit_price'];
        $unitSize = (int)$settings['paid_tier_unit_size'];
        $totalCost = round($blocks * $unitPrice, 2);

        $this->jsonSuccess([
            'added_blocks' => $blocks,
            'added_capacity' => $blocks * $unitSize,
            'amount_charged' => $totalCost,
            'quota' => $freshQuota,
        ], "Plan upgraded successfully! +{$blocks} block (" . ($blocks * $unitSize) . " slots) added.");
    }
}
