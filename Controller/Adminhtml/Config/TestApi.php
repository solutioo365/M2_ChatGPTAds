<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Controller\Adminhtml\Config;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Controller\Adminhtml\Action;
use Solutioo\ChatGptProductSearch\Model\Api\OpenAiClient;
use Solutioo\ChatGptProductSearch\Model\Config;

class TestApi extends Action
{
    public const ADMIN_RESOURCE = 'Solutioo_ChatGptProductSearch::config';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly OpenAiClient $openAiClient
    ) {
        parent::__construct($context, $jsonFactory, $storeManager);
    }

    public function execute(): Json
    {
        try {
            $storeId = $this->storeId();
            $feedId = $this->config->getOpenAiFeedId($storeId);
            if ($this->config->isAdsApi($storeId)) {
                $account = $this->openAiClient->getAdAccount($storeId);
                return $this->json([
                    'success' => true,
                    'message' => (string) __(
                        'Ads-API erreichbar. Account %1 (%2), Feed %3.',
                        $account['name'] ?? '',
                        $account['id'] ?? '',
                        $feedId !== '' ? $feedId : __('nicht gesetzt')
                    ),
                    'account' => $account,
                    'feed_id' => $feedId,
                ]);
            }
            if ($feedId === '') {
                return $this->json([
                    'success' => true,
                    'message' => (string) __('API-Key ist gesetzt. Noch keine Feed-ID – in der Übersicht „Feed anlegen“ nutzen.'),
                ]);
            }
            $meta = $this->openAiClient->getFeed($storeId, $feedId);
            return $this->json([
                'success' => true,
                'message' => (string) __('API erreichbar. Feed %1, Land %2.', $meta['id'] ?? $feedId, $meta['target_country'] ?? ''),
                'feed' => $meta,
            ]);
        } catch (\Throwable $exception) {
            return $this->jsonError($exception);
        }
    }
}
