<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Feed;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Solutioo\ChatGptProductSearch\Model\Api\OpenAiClient;
use Solutioo\ChatGptProductSearch\Model\Config;
use Solutioo\ChatGptProductSearch\Model\Logging\FeedLogger;
use Solutioo\ChatGptProductSearch\Model\Sftp\Uploader;

class Publisher
{
    public function __construct(
        private readonly Config $config,
        private readonly Generator $generator,
        private readonly Uploader $sftpUploader,
        private readonly OpenAiClient $openAiClient,
        private readonly FeedLogger $feedLogger,
        private readonly WriterInterface $configWriter
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(int $storeId, bool $uploadSftp = false, bool $syncApi = false): array
    {
        $result = $this->generator->generate($storeId);
        if ($uploadSftp && $this->config->isSftpEnabled($storeId)) {
            $result['sftp'] = $this->sftpUploader->upload($storeId);
        }
        if ($syncApi && $this->config->isApiEnabled($storeId)) {
            $result['api'] = $this->syncApi($storeId, $result['products'] ?? [], $result['promotions'] ?? []);
        }
        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function publishScheduled(int $storeId): array
    {
        return $this->publish(
            $storeId,
            $this->config->autoUploadSftp($storeId),
            $this->config->autoSyncApi($storeId)
        );
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param array<int, array<string, mixed>> $promotions
     * @return array<string, mixed>
     */
    public function syncApi(int $storeId, array $products = [], array $promotions = [], ?string $sku = null): array
    {
        if (!$this->config->isApiEnabled($storeId)) {
            throw new \RuntimeException((string) __('OpenAI API-Sync ist nicht aktiviert.'));
        }

        $feedId = $this->config->getOpenAiFeedId($storeId);
        if ($feedId === '') {
            $created = $this->openAiClient->createFeed($storeId);
            $feedId = (string) ($created['id'] ?? '');
            if ($feedId === '') {
                throw new \RuntimeException((string) __('OpenAI hat keine Feed-ID zurückgegeben.'));
            }
            $this->configWriter->save('solutioo_chatgpt/api/feed_id', $feedId);
            $this->config->clean();
        }

        if ($sku !== null && $sku !== '') {
            $products = $this->generator->mapSku($storeId, $sku);
            $promotions = [];
        } elseif ($products === []) {
            $generated = $this->generator->generate($storeId);
            $products = $generated['products'] ?? [];
            $promotions = $generated['promotions'] ?? [];
        }

        $batchSize = $this->config->getApiBatchSize($storeId);
        $accepted = 0;
        $rejected = 0;
        $lastResponse = [];
        $batches = array_chunk($products, $batchSize);
        foreach ($batches as $batch) {
            $apiProducts = array_map(
                fn (array $product): array => $this->toApiProduct($product, $storeId),
                $batch
            );
            $response = $this->openAiClient->upsertProducts($storeId, $feedId, $apiProducts);
            $lastResponse = $response;
            if (!empty($response['accepted'])) {
                $accepted += count($batch);
            } else {
                $rejected += count($batch);
            }
        }

        if ($promotions !== [] && !$this->config->isAdsApi($storeId)) {
            $this->openAiClient->upsertPromotions($storeId, $feedId, $promotions);
        }

        $status = $rejected > 0 && $accepted === 0 ? 'warning' : 'success';
        $message = $sku
            ? (string) __('API-Sync SKU %1: %2 akzeptiert, %3 nicht übernommen (Feed %4).', $sku, $accepted, $rejected, $feedId)
            : (string) __('API-Sync: %1 Produkte an Feed %2 übertragen.', $accepted, $feedId);
        if ($this->config->isAdsApi($storeId) && $rejected > 0) {
            $message .= ' ' . (string) __(
                'Die Ads-Delta-API aktualisiert nur bereits im Feed vorhandene Varianten. Neue Artikel brauchen zuerst einen SFTP-Katalog.'
            );
        }

        $result = [
            'success' => $accepted > 0 || $rejected === 0,
            'feed_id' => $feedId,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'product_count' => count($products),
            'sku' => $sku,
            'response' => $lastResponse,
            'message' => $message,
        ];
        $this->feedLogger->log($storeId, 'api_sync', $status, $result['message'], $result);
        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function createApiFeed(int $storeId): array
    {
        $created = $this->openAiClient->createFeed($storeId);
        $feedId = (string) ($created['id'] ?? '');
        if ($feedId !== '') {
            $this->configWriter->save('solutioo_chatgpt/api/feed_id', $feedId);
            $this->config->clean();
        }
        $this->feedLogger->log(
            $storeId,
            'api_create',
            $feedId !== '' ? 'success' : 'error',
            $feedId !== '' ? (string) __('Feed %1 angelegt.', $feedId) : (string) __('Feed-Anlage fehlgeschlagen.'),
            $created
        );
        return $created;
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function toApiProduct(array $product, int $storeId = 0): array
    {
        if ($this->config->isAdsApi($storeId)) {
            return $this->toAdsApiProduct($product);
        }
        unset($product['is_eligible_search'], $product['is_eligible_checkout']);
        return $product;
    }

    /**
     * Ads-Delta-API akzeptiert nur id, title, price und availability je vorhandener Variante.
     *
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function toAdsApiProduct(array $product): array
    {
        $variants = [];
        foreach ($product['variants'] ?? [] as $variant) {
            if (!is_array($variant) || ($variant['id'] ?? '') === '') {
                continue;
            }
            $row = ['id' => (string) $variant['id']];
            if (($variant['title'] ?? '') !== '') {
                $row['title'] = (string) $variant['title'];
            }
            if (isset($variant['price']) && is_array($variant['price'])) {
                $row['price'] = $variant['price'];
            }
            if (isset($variant['availability']) && is_array($variant['availability'])) {
                $row['availability'] = $variant['availability'];
            }
            $variants[] = $row;
        }
        return [
            'id' => (string) ($product['id'] ?? ''),
            'variants' => $variants,
        ];
    }
}
