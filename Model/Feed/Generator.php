<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Feed;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Solutioo\ChatGptProductSearch\Model\Config;
use Solutioo\ChatGptProductSearch\Model\Config\Source\FeedFormat;
use Solutioo\ChatGptProductSearch\Model\Feed\Writer\DelimitedWriter;
use Solutioo\ChatGptProductSearch\Model\Feed\Writer\JsonlWriter;
use Solutioo\ChatGptProductSearch\Model\Logging\FeedLogger;

class Generator
{
    public function __construct(
        private readonly Config $config,
        private readonly CatalogCollector $collector,
        private readonly ProductMapper $mapper,
        private readonly PromotionBuilder $promotionBuilder,
        private readonly Validator $validator,
        private readonly FileStorage $storage,
        private readonly JsonlWriter $jsonlWriter,
        private readonly DelimitedWriter $delimitedWriter,
        private readonly FeedLogger $feedLogger,
        private readonly EventManager $eventManager
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(int $storeId): array
    {
        $started = microtime(true);
        $catalog = $this->collector->collect($storeId);
        $products = [];
        $flatRows = [];

        $sampleLimit = $this->config->getSampleLimit($storeId);

        /** @var Product $parent */
        foreach ($catalog['parents'] as $parent) {
            $children = $catalog['children'][(string) $parent->getSku()] ?? [];
            $mapped = $this->mapper->mapProduct($parent, $children, $storeId);
            if ($this->config->excludeIncomplete($storeId)
                && $this->validator->validateProducts([$mapped], $storeId)['invalid'] > 0
            ) {
                continue;
            }
            $products[] = $mapped;

            if ($parent->getTypeId() === Configurable::TYPE_CODE && $children !== []) {
                foreach ($children as $child) {
                    $flatRows[] = $this->mapper->mapFlatRow($child, $parent, $storeId);
                }
            } else {
                $flatRows[] = $this->mapper->mapFlatRow($parent, $parent, $storeId);
            }

            if ($sampleLimit > 0 && count($products) >= $sampleLimit) {
                break;
            }
        }

        $promotions = $this->promotionBuilder->build($storeId);
        $report = $this->validator->validateProducts($products, $storeId);
        $format = $this->config->getFeedFormat($storeId);
        $payload = $this->encodeProducts($products, $flatRows, $format);
        $payload = $this->maybeGzip($payload, $storeId);

        $productsPath = $this->storage->getProductsRelativePath($storeId, $format);
        $absolute = $this->storage->write($productsPath, $payload);

        $promotionsRelative = $this->storage->getPromotionsRelativePath($storeId);
        $promoPayload = $this->maybeGzip($this->jsonlWriter->write($promotions), $storeId);
        $this->storage->write($promotionsRelative, $promoPayload);

        $header = [
            'feed_id' => $this->config->getOpenAiFeedId($storeId),
            'account_id' => $this->config->getOpenAiAccountId($storeId),
            'target_merchant' => $this->config->getOpenAiMerchantId($storeId),
            'target_country' => $this->config->getTargetCountry($storeId),
            'generated_at' => gmdate('c'),
            'store_id' => $storeId,
            'format' => $format,
            'product_count' => count($products),
            'variant_count' => count($flatRows),
        ];
        $this->storage->write(
            $this->storage->getHeaderRelativePath($storeId),
            json_encode($header, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        $result = [
            'store_id' => $storeId,
            'format' => $format,
            'file' => $absolute,
            'relative_file' => $productsPath,
            'promotions_file' => $this->storage->getAbsolutePath($promotionsRelative),
            'product_count' => count($products),
            'variant_count' => count($flatRows),
            'promotion_count' => count($promotions),
            'valid' => $report['valid'],
            'invalid' => $report['invalid'],
            'errors' => $report['errors'],
            'warnings' => $report['warnings'],
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'products' => $products,
            'promotions' => $promotions,
        ];

        $this->feedLogger->log(
            $storeId,
            'generate',
            $report['invalid'] > 0 ? 'warning' : 'success',
            (string) __('Feed erzeugt: %1 Produkte, %2 Varianten.', count($products), count($flatRows)),
            $result
        );

        $this->eventManager->dispatch('solutioo_chatgpt_feed_generate_after', [
            'store_id' => $storeId,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * Mappt eine einzelne SKU ohne Feed-Datei zu schreiben.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mapSku(int $storeId, string $sku): array
    {
        $catalog = $this->collector->collectBySku($storeId, $sku);
        $products = [];
        foreach ($catalog['parents'] as $parent) {
            $children = $catalog['children'][(string) $parent->getSku()] ?? [];
            $products[] = $this->mapper->mapProduct($parent, $children, $storeId);
        }
        return $products;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<string, mixed>
     */
    public function preview(int $storeId, int $limit = 10): array
    {
        $catalog = $this->collector->collect($storeId);
        $products = [];
        $i = 0;
        foreach ($catalog['parents'] as $parent) {
            $children = $catalog['children'][(string) $parent->getSku()] ?? [];
            $products[] = $this->mapper->mapProduct($parent, $children, $storeId);
            $i++;
            if ($i >= $limit) {
                break;
            }
        }
        $report = $this->validator->validateProducts($products, $storeId);
        return [
            'products' => $products,
            'count' => count($products),
            'errors' => $report['errors'],
            'warnings' => $report['warnings'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<string, mixed>
     */
    public function validateOnly(int $storeId): array
    {
        $catalog = $this->collector->collect($storeId);
        $products = [];
        foreach ($catalog['parents'] as $parent) {
            $children = $catalog['children'][(string) $parent->getSku()] ?? [];
            $products[] = $this->mapper->mapProduct($parent, $children, $storeId);
        }
        $report = $this->validator->validateProducts($products, $storeId);
        $this->feedLogger->log(
            $storeId,
            'validate',
            $report['invalid'] > 0 ? 'warning' : 'success',
            (string) __('Validierung: %1 gültig, %2 ungültig.', $report['valid'], $report['invalid']),
            $report + ['product_count' => count($products)]
        );
        return $report + ['product_count' => count($products)];
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param array<int, array<string, mixed>> $flatRows
     */
    private function encodeProducts(array $products, array $flatRows, string $format): string
    {
        if ($format === FeedFormat::JSONL) {
            return $this->jsonlWriter->write($this->toOpenAiFlatRecords($flatRows));
        }
        return $this->delimitedWriter->write($flatRows, $format);
    }

    /**
     * @param array<int, array<string, mixed>> $flatRows
     * @return array<int, array<string, mixed>>
     */
    private function toOpenAiFlatRecords(array $flatRows): array
    {
        $keys = [
            'is_eligible_search',
            'is_eligible_checkout',
            'is_ads_eligible',
            'item_id',
            'gtin',
            'mpn',
            'identifier_exists',
            'title',
            'description',
            'url',
            'brand',
            'condition',
            'product_category',
            'material',
            'weight',
            'item_weight_unit',
            'age_group',
            'image_url',
            'additional_image_urls',
            'price',
            'sale_price',
            'sale_price_start_date',
            'sale_price_end_date',
            'availability',
            'group_id',
            'listing_has_variations',
            'variant_dict',
            'item_group_title',
            'color',
            'size',
            'gender',
            'is_digital',
            'seller_name',
            'seller_url',
            'seller_privacy_policy',
            'seller_tos',
            'accepts_returns',
            'return_deadline_in_days',
            'accepts_exchanges',
            'return_policy',
            'review_count',
            'star_rating',
            'target_countries',
            'store_country',
            'id',
            'link',
            'image_link',
            'additional_image_link',
            'item_group_id',
            'google_product_category',
            'product_type',
        ];

        $records = [];
        foreach ($flatRows as $row) {
            $record = [];
            foreach ($keys as $key) {
                if (!array_key_exists($key, $row) || $row[$key] === '' || $row[$key] === null) {
                    continue;
                }
                $record[$key] = $row[$key];
            }
            $records[] = $record;
        }
        return $records;
    }

    private function maybeGzip(string $payload, int $storeId): string
    {
        if (!$this->config->compressOutput($storeId)) {
            return $payload;
        }
        $gz = gzencode($payload, 9);
        if ($gz === false) {
            throw new \RuntimeException('Gzip-Kompression fehlgeschlagen.');
        }
        return $gz;
    }
}
