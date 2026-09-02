<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Controller\Adminhtml\Action;
use Solutioo\ChatGptProductSearch\Model\Feed\Generator;

class Generate extends Action
{
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        StoreManagerInterface $storeManager,
        private readonly Generator $generator
    ) {
        parent::__construct($context, $jsonFactory, $storeManager);
    }

    public function execute(): Json
    {
        try {
            $result = $this->generator->generate($this->storeId());
            unset($result['products'], $result['promotions']);
            $result['message'] = (string) __(
                'Feed erzeugt: %1 Produkte, %2 Varianten.',
                $result['product_count'],
                $result['variant_count']
            );
            return $this->json($result);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception);
        }
    }
}
