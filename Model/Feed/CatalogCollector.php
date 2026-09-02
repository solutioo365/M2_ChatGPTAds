<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Feed;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Model\AttributeCodes;
use Solutioo\ChatGptProductSearch\Model\Config;

class CatalogCollector
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Config $config,
        private readonly StockHelper $stockHelper,
        private readonly Configurable $configurableType,
        private readonly StoreManagerInterface $storeManager,
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * @return array{parents: Product[], children: array<string, Product[]>, used_child_ids: int[]}
     */
    public function collect(int $storeId): array
    {
        $collection = $this->createBaseCollection($storeId);
        $parents = [];
        $usedChildIds = [];
        $childrenByParent = [];

        foreach ($collection as $product) {
            /** @var Product $product */
            if ($product->getTypeId() === Configurable::TYPE_CODE && !$this->config->exportParentsOnly($storeId)) {
                $childProducts = $this->loadConfigurableChildren($product, $storeId);
                $childrenByParent[(string) $product->getSku()] = $childProducts;
                foreach ($childProducts as $child) {
                    $usedChildIds[] = (int) $child->getId();
                }
                if ($childProducts === [] && !$this->config->includeOutOfStock($storeId)) {
                    continue;
                }
            }
            $parents[] = $product;
        }

        $usedChildIds = array_values(array_unique($usedChildIds));
        if ($usedChildIds !== []) {
            $parents = array_values(array_filter(
                $parents,
                static fn (Product $product): bool => !in_array((int) $product->getId(), $usedChildIds, true)
            ));
        }

        return [
            'parents' => $parents,
            'children' => $childrenByParent,
            'used_child_ids' => $usedChildIds,
        ];
    }

    /**
     * Lädt genau eine SKU (Simple oder Configurable inkl. Kinder) für den API-Einzelupload.
     *
     * @return array{parents: Product[], children: array<string, Product[]>, used_child_ids: int[]}
     */
    public function collectBySku(int $storeId, string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            throw new \InvalidArgumentException((string) __('SKU fehlt.'));
        }

        $product = $this->loadBySku($storeId, $sku);
        if ($product === null) {
            throw new \RuntimeException((string) __('Produkt %1 nicht gefunden.', $sku));
        }

        $parent = $product;
        if ($product->getTypeId() !== Configurable::TYPE_CODE) {
            $parentIds = $this->configurableType->getParentIdsByChild((int) $product->getId());
            $parentId = (int) ($parentIds[0] ?? 0);
            if ($parentId > 0) {
                $loadedParent = $this->loadById($storeId, $parentId);
                if ($loadedParent !== null) {
                    $parent = $loadedParent;
                }
            }
        }

        $children = [];
        $usedChildIds = [];
        if ($parent->getTypeId() === Configurable::TYPE_CODE) {
            $children = $this->loadConfigurableChildren($parent, $storeId);
            foreach ($children as $child) {
                $usedChildIds[] = (int) $child->getId();
            }
        }

        return [
            'parents' => [$parent],
            'children' => [(string) $parent->getSku() => $children],
            'used_child_ids' => $usedChildIds,
        ];
    }

    private function loadBySku(int $storeId, string $sku): ?Product
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect($this->getSelectAttributes($storeId))
            ->addUrlRewrite()
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToFilter('sku', $sku)
            ->setPageSize(1);
        $product = $collection->getFirstItem();
        return $product && $product->getId() ? $product : null;
    }

    private function loadById(int $storeId, int $productId): ?Product
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect($this->getSelectAttributes($storeId))
            ->addUrlRewrite()
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addIdFilter([$productId])
            ->setPageSize(1);
        $product = $collection->getFirstItem();
        return $product && $product->getId() ? $product : null;
    }

    public function createBaseCollection(int $storeId): Collection
    {
        $store = $this->storeManager->getStore($storeId);
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addWebsiteFilter((int) $store->getWebsiteId())
            ->addAttributeToSelect($this->getSelectAttributes($storeId))
            ->addUrlRewrite()
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents();

        if (!$this->config->includeDisabled($storeId)) {
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        }

        $visibility = $this->config->getVisibilityIds($storeId);
        if ($visibility !== []) {
            $collection->addAttributeToFilter('visibility', ['in' => $visibility]);
        }

        if ($this->config->onlyEligibleSearch($storeId) && $this->hasAttribute(AttributeCodes::SEARCH)) {
            $collection->addAttributeToFilter(AttributeCodes::SEARCH, 1);
        }

        if ($this->hasAttribute(AttributeCodes::EXCLUDE)) {
            $collection->addAttributeToFilter(
                [
                    ['attribute' => AttributeCodes::EXCLUDE, 'neq' => 1],
                    ['attribute' => AttributeCodes::EXCLUDE, 'null' => true],
                ],
                null,
                'left'
            );
        }

        $include = $this->config->getIncludeCategoryIds($storeId);
        if ($include !== []) {
            $collection->addCategoriesFilter(['in' => $include]);
        }
        $exclude = $this->config->getExcludeCategoryIds($storeId);
        if ($exclude !== []) {
            $collection->addCategoriesFilter(['nin' => $exclude]);
        }

        if (!$this->config->includeOutOfStock($storeId)) {
            $this->stockHelper->addInStockFilterToCollection($collection);
        }

        $limit = $this->config->getSampleLimit($storeId);
        if ($limit > 0) {
            $collection->setPageSize(max($limit * 20, 50));
        }

        return $collection;
    }

    /**
     * @return Product[]
     */
    private function loadConfigurableChildren(Product $parent, int $storeId): array
    {
        $children = $this->configurableType->getUsedProducts($parent);
        $result = [];
        foreach ($children as $child) {
            /** @var Product $child */
            $child->setStoreId($storeId);
            if (!$this->config->includeDisabled($storeId) && (int) $child->getStatus() !== Status::STATUS_ENABLED) {
                continue;
            }
            $result[] = $child;
        }
        return $result;
    }

    /**
     * @return string[]
     */
    private function getSelectAttributes(int $storeId): array
    {
        $attributes = [
            'name',
            'sku',
            'description',
            'short_description',
            'image',
            'small_image',
            'thumbnail',
            'media_gallery',
            'price',
            'special_price',
            'special_from_date',
            'special_to_date',
            'status',
            'visibility',
            'weight',
            'url_key',
            AttributeCodes::SEARCH,
            AttributeCodes::CHECKOUT,
            AttributeCodes::EXCLUDE,
            AttributeCodes::GTIN,
            AttributeCodes::MPN,
        ];

        foreach ([
            $this->config->getBrandAttribute($storeId),
            $this->config->getGtinAttribute($storeId),
            $this->config->getMpnAttribute($storeId),
            $this->config->getConditionAttribute($storeId),
            $this->config->getGoogleCategoryAttribute($storeId),
            $this->config->getColorAttribute($storeId),
            $this->config->getSizeAttribute($storeId),
            $this->config->getMaterialAttribute($storeId),
            $this->config->getGenderAttribute($storeId),
            $this->config->getAgeGroupAttribute($storeId),
            'ean',
            'gtin',
            'manufacturer',
        ] as $code) {
            if ($code !== '') {
                $attributes[] = $code;
            }
        }

        return array_values(array_unique($attributes));
    }

    private function hasAttribute(string $code): bool
    {
        try {
            $attribute = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $code);
            return (bool) $attribute->getId();
        } catch (\Throwable) {
            return false;
        }
    }
}
