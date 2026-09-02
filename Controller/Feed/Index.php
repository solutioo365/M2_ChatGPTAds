<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Controller\Feed;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Model\Config;
use Solutioo\ChatGptProductSearch\Model\Feed\FileStorage;
use Solutioo\ChatGptProductSearch\Model\Feed\Generator;

class Index extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly FileStorage $storage,
        private readonly Generator $generator,
        private readonly RawFactory $rawFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute(): Raw
    {
        $result = $this->rawFactory->create();
        $storeId = (int) $this->getRequest()->getParam('store', $this->storeManager->getStore()->getId());
        $token = (string) $this->getRequest()->getParam('token', '');

        if (!$this->config->isEnabled($storeId) || !$this->config->getHttpsEnabled($storeId)) {
            return $result->setHttpResponseCode(404)->setContents('Feed disabled');
        }

        $expected = $this->config->getFeedToken($storeId);
        if ($expected === '' || !hash_equals($expected, $token)) {
            return $result->setHttpResponseCode(403)->setContents('Forbidden');
        }

        $relative = $this->storage->getProductsRelativePath($storeId);
        if ($this->config->generateOnRequest($storeId) || !$this->storage->exists($relative)) {
            $this->generator->generate($storeId);
            $relative = $this->storage->getProductsRelativePath($storeId);
        }

        if (!$this->storage->exists($relative)) {
            return $result->setHttpResponseCode(404)->setContents('Feed not generated');
        }

        $contents = $this->storage->read($relative);
        $this->getResponse()->setHeader('Content-Type', $this->storage->mimeType($relative), true);
        $this->getResponse()->setHeader('Content-Disposition', 'inline; filename="' . basename($relative) . '"', true);
        $this->getResponse()->setHeader('Cache-Control', 'public, max-age=300', true);
        return $result->setContents($contents);
    }
}
