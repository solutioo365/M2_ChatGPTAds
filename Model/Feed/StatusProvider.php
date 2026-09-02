<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Feed;

use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Model\Config;
use Solutioo\ChatGptProductSearch\Model\ResourceModel\FeedLog\CollectionFactory as LogCollectionFactory;

class StatusProvider
{
    public function __construct(
        private readonly Config $config,
        private readonly FileStorage $storage,
        private readonly StoreManagerInterface $storeManager,
        private readonly LogCollectionFactory $logCollectionFactory
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllStores(): array
    {
        $rows = [];
        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }
            $rows[] = $this->getStoreStatus((int) $store->getId());
        }
        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStoreStatus(int $storeId): array
    {
        $store = $this->storeManager->getStore($storeId);
        $relative = $this->storage->getProductsRelativePath($storeId);
        $exists = $this->storage->exists($relative);
        $absolute = $exists ? $this->storage->getAbsolutePath($relative) : '';
        $mtime = $exists ? @filemtime($absolute) : null;
        $size = $exists ? @filesize($absolute) : 0;
        $last = $this->lastLog($storeId);

        $checklist = $this->checklist($storeId, $exists);

        return [
            'store_id' => $storeId,
            'store_code' => $store->getCode(),
            'store_name' => $store->getName(),
            'enabled' => $this->config->isEnabled($storeId),
            'format' => $this->config->getFeedFormat($storeId),
            'variant_mode' => $this->config->getVariantMode($storeId),
            'feed_url' => $this->config->getPublicFeedUrl($storeId),
            'has_file' => $exists,
            'file' => $absolute,
            'file_size' => (int) $size,
            'generated_at' => $mtime ? date('c', $mtime) : null,
            'https_enabled' => $this->config->getHttpsEnabled($storeId),
            'sftp_enabled' => $this->config->isSftpEnabled($storeId),
            'api_enabled' => $this->config->isApiEnabled($storeId),
            'api_feed_id' => $this->config->getOpenAiFeedId($storeId),
            'cron_enabled' => $this->config->isCronEnabled(),
            'cron_expr' => $this->config->getCronExpr(),
            'seller_name' => $this->config->getSellerName($storeId),
            'target_country' => $this->config->getTargetCountry($storeId),
            'last_log' => $last,
            'checklist' => $checklist,
            'readiness' => $this->readiness($checklist),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastLog(int $storeId): ?array
    {
        $collection = $this->logCollectionFactory->create();
        $collection->addFieldToFilter('store_id', $storeId)
            ->setOrder('created_at', 'DESC')
            ->setPageSize(1);
        $item = $collection->getFirstItem();
        if (!$item || !$item->getId()) {
            return null;
        }
        return [
            'action' => (string) $item->getData('action'),
            'status' => (string) $item->getData('status'),
            'message' => (string) $item->getData('message'),
            'product_count' => (int) $item->getData('product_count'),
            'error_count' => (int) $item->getData('error_count'),
            'warning_count' => (int) $item->getData('warning_count'),
            'created_at' => (string) $item->getData('created_at'),
        ];
    }

    /**
     * @return array<int, array{id: string, ok: bool, label: string}>
     */
    private function checklist(int $storeId, bool $hasFile): array
    {
        return [
            ['id' => 'enabled', 'ok' => $this->config->isEnabled($storeId), 'label' => (string) __('Modul für diesen Store aktiv')],
            ['id' => 'seller', 'ok' => $this->config->getSellerName($storeId) !== '', 'label' => (string) __('Händlername gesetzt')],
            ['id' => 'policies', 'ok' => $this->config->getPrivacyPolicyUrl($storeId) !== '' && $this->config->getTermsUrl($storeId) !== '', 'label' => (string) __('Datenschutz- und AGB-URL')],
            ['id' => 'returns', 'ok' => $this->config->getReturnPolicyUrl($storeId) !== '', 'label' => (string) __('Rückgabe-URL')],
            ['id' => 'token', 'ok' => $this->config->getFeedToken($storeId) !== '', 'label' => (string) __('Feed-Token erzeugt')],
            ['id' => 'file', 'ok' => $hasFile, 'label' => (string) __('Feed-Datei vorhanden')],
            ['id' => 'delivery', 'ok' => $this->config->isSftpEnabled($storeId) || $this->config->isApiEnabled($storeId) || $this->config->getHttpsEnabled($storeId), 'label' => (string) __('Mindestens ein Lieferweg (HTTPS / SFTP / API)')],
            ['id' => 'cron', 'ok' => $this->config->isCronEnabled(), 'label' => (string) __('Cron-Zeitplan aktiv')],
        ];
    }

    /**
     * @param array<int, array{ok: bool}> $checklist
     */
    private function readiness(array $checklist): int
    {
        $total = count($checklist);
        if ($total === 0) {
            return 0;
        }
        $ok = count(array_filter($checklist, static fn (array $item): bool => $item['ok']));
        return (int) round(($ok / $total) * 100);
    }
}
