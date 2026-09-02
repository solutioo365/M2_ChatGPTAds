<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Controller\Adminhtml;

use Magento\Backend\App\Action as BackendAction;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

abstract class Action extends BackendAction
{
    public const ADMIN_RESOURCE = 'Solutioo_ChatGptProductSearch::feed';

    public function __construct(
        Context $context,
        protected readonly JsonFactory $jsonFactory,
        protected readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    protected function storeId(): int
    {
        $storeId = (int) $this->getRequest()->getParam('store_id');
        if ($storeId > 0) {
            return $storeId;
        }
        return (int) $this->storeManager->getDefaultStoreView()?->getId() ?: 1;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function json(array $data, bool $success = true): Json
    {
        $result = $this->jsonFactory->create();
        $data['success'] = $data['success'] ?? $success;
        return $result->setData($data);
    }

    protected function jsonError(\Throwable $exception): Json
    {
        return $this->json([
            'success' => false,
            'message' => $exception->getMessage(),
        ], false);
    }
}
