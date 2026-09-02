<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Solutioo\ChatGptProductSearch\Model\Feed\StatusProvider;
use Solutioo\ChatGptProductSearch\Model\ResourceModel\FeedLog\CollectionFactory as LogCollectionFactory;

class Dashboard extends Template
{
    public function __construct(
        Context $context,
        private readonly StatusProvider $statusProvider,
        private readonly LogCollectionFactory $logCollectionFactory,
        private readonly ProductMetadataInterface $productMetadata,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStores(): array
    {
        return $this->statusProvider->getAllStores();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentLogs(int $limit = 8): array
    {
        $collection = $this->logCollectionFactory->create();
        $collection->setOrder('created_at', 'DESC')->setPageSize($limit);
        $rows = [];
        foreach ($collection as $item) {
            $rows[] = $item->getData();
        }
        return $rows;
    }

    public function getGenerateUrl(): string
    {
        return $this->getUrl('solutioo_chatgpt/feed/generate');
    }

    public function getValidateUrl(): string
    {
        return $this->getUrl('solutioo_chatgpt/feed/validate');
    }

    public function getPreviewUrl(): string
    {
        return $this->getUrl('solutioo_chatgpt/feed/preview');
    }

    public function getDownloadUrl(int $storeId): string
    {
        return $this->getUrl('solutioo_chatgpt/feed/download', ['store_id' => $storeId]);
    }

    public function getUploadUrl(): string
    {
        return $this->getUrl('solutioo_chatgpt/feed/upload');
    }

    public function getSyncApiUrl(): string
    {
        return $this->getUrl('solutioo_chatgpt/feed/syncApi');
    }

    public function getCreateFeedUrl(): string
    {
        return $this->getUrl('solutioo_chatgpt/feed/createApiFeed');
    }

    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'solutioo_chatgpt']);
    }

    public function getLogUrl(): string
    {
        return $this->getUrl('solutioo_chatgpt/log/index');
    }

    public function getMerchantUrl(): string
    {
        return 'https://chatgpt.com/de-DE/merchants/';
    }

    public function getDocsUrl(): string
    {
        return 'https://developers.openai.com/commerce';
    }

    public function getModuleVersion(): string
    {
        return '1.0.5';
    }

    public function getMagentoVersion(): string
    {
        return $this->productMetadata->getName() . ' ' . $this->productMetadata->getVersion();
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '–';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        return number_format($bytes / (1024 ** $i), 1, ',', '.') . ' ' . $units[$i];
    }
}
