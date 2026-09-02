<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Cron;

use Psr\Log\LoggerInterface;
use Solutioo\ChatGptProductSearch\Model\Config;
use Solutioo\ChatGptProductSearch\Model\Feed\Publisher;
use Solutioo\ChatGptProductSearch\Model\Feed\StatusProvider;

class PublishFeed
{
    public function __construct(
        private readonly Config $config,
        private readonly Publisher $publisher,
        private readonly StatusProvider $statusProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isCronEnabled()) {
            return;
        }

        foreach ($this->statusProvider->getAllStores() as $store) {
            $storeId = (int) $store['store_id'];
            if (!$this->config->isEnabled($storeId)) {
                continue;
            }
            try {
                $this->publisher->publishScheduled($storeId);
            } catch (\Throwable $exception) {
                $this->logger->error(sprintf(
                    '[Solutioo_ChatGptProductSearch] Cron store %d failed: %s',
                    $storeId,
                    $exception->getMessage()
                ));
            }
        }
    }
}
