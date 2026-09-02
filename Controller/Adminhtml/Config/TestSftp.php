<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Controller\Adminhtml\Config;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Controller\Adminhtml\Action;
use Solutioo\ChatGptProductSearch\Model\Sftp\Uploader;

class TestSftp extends Action
{
    public const ADMIN_RESOURCE = 'Solutioo_ChatGptProductSearch::config';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        StoreManagerInterface $storeManager,
        private readonly Uploader $uploader
    ) {
        parent::__construct($context, $jsonFactory, $storeManager);
    }

    public function execute(): Json
    {
        try {
            return $this->json($this->uploader->test($this->storeId()));
        } catch (\Throwable $exception) {
            return $this->jsonError($exception);
        }
    }
}
