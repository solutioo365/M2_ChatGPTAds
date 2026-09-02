<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Feed;

use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;
use Magento\SalesRule\Model\Rule;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Model\Config;

class PromotionBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly RuleCollectionFactory $ruleCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Money $money
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(int $storeId): array
    {
        if (!$this->config->includePromotions($storeId)) {
            return [];
        }

        $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();
        $collection = $this->ruleCollectionFactory->create();
        $collection->addWebsiteFilter($websiteId)
            ->addFieldToFilter('is_active', 1);

        $promotions = [];
        foreach ($collection as $rule) {
            /** @var Rule $rule */
            $mapped = $this->mapRule($rule, $storeId);
            if ($mapped !== null) {
                $promotions[] = $mapped;
            }
        }
        return $promotions;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapRule(Rule $rule, int $storeId): ?array
    {
        $benefit = $this->mapBenefit($rule, $storeId);
        if ($benefit === null) {
            return null;
        }

        $from = $rule->getFromDate() ?: '2020-01-01 00:00:00';
        $to = $rule->getToDate() ?: '2099-12-31 23:59:59';

        $status = 'active';
        $now = time();
        $startTs = strtotime((string) $from) ?: $now;
        $endTs = strtotime((string) $to) ?: $now;
        if ($startTs > $now) {
            $status = 'scheduled';
        } elseif ($endTs < $now) {
            $status = 'expired';
        }

        $description = trim(strip_tags((string) $rule->getDescription()));
        $promotion = [
            'id' => 'rule-' . $rule->getId(),
            'title' => (string) $rule->getName(),
            'status' => $status,
            'active_period' => [
                'start_time' => gmdate('c', $startTs),
                'end_time' => gmdate('c', $endTs),
            ],
            'benefits' => [$benefit],
        ];
        if ($description !== '') {
            $promotion['description'] = ['plain' => $description];
        }
        return $promotion;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapBenefit(Rule $rule, int $storeId): ?array
    {
        $action = (string) $rule->getSimpleAction();
        $amount = (float) $rule->getDiscountAmount();
        $currency = strtoupper((string) $this->storeManager->getStore($storeId)->getCurrentCurrencyCode());

        return match ($action) {
            'by_percent' => ['type' => 'percent_off', 'percent_off' => $amount],
            'by_fixed', 'cart_fixed' => [
                'type' => 'amount_off',
                'amount_off' => [
                    'amount' => $this->money->toMinorUnits($amount, $currency),
                    'currency' => $currency,
                ],
            ],
            default => ((int) $rule->getSimpleFreeShipping() > 0)
                ? ['type' => 'free_shipping']
                : null,
        };
    }
}
