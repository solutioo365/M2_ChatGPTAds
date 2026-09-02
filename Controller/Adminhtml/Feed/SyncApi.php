<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Controller\Adminhtml\Action;
use Solutioo\ChatGptProductSearch\Model\Feed\Publisher;

class SyncApi extends Action
{
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        StoreManagerInterface $storeManager,
        private readonly Publisher $publisher
    ) {
        parent::__construct($context, $jsonFactory, $storeManager);
    }

    public function execute(): Json
    {
        try {
            return $this->json($this->publisher->syncApi($this->storeId()));
        } catch (\Throwable $exception) {
            return $this->jsonError($exception);
        }
    }
}
