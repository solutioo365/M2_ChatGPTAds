<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Feed;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\UrlInterface;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Model\AttributeCodes;
use Solutioo\ChatGptProductSearch\Model\Config;

class ProductMapper
{
    public function __construct(
        private readonly Config $config,
        private readonly Money $money,
        private readonly ImageHelper $imageHelper,
        private readonly GalleryReadHandler $galleryReadHandler,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly ReviewFactory $reviewFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly EventManager $eventManager,
        private readonly StringUtils $string
    ) {
    }

    /**
     * @param Product[] $children
     * @return array<string, mixed>
     */
    public function mapProduct(Product $product, array $children, int $storeId): array
    {
        $variants = [];
        if ($product->getTypeId() === Configurable::TYPE_CODE && $children !== []) {
            foreach ($children as $child) {
                $variants[] = $this->mapVariant($child, $product, $storeId);
            }
        } else {
            $variants[] = $this->mapVariant($product, $product, $storeId);
        }

        $mapped = [
            'id' => (string) $product->getSku(),
            'title' => $this->clip((string) $product->getName(), 150),
            'description' => $this->mapDescription($product),
            'url' => $this->productUrl($product, $storeId),
            'media' => $this->mapMedia($product),
            'variants' => $variants,
            'is_eligible_search' => $this->isSearchEligible($product, $storeId),
            'is_eligible_checkout' => $this->isCheckoutEligible($product, $storeId),
        ];

        $this->eventManager->dispatch('solutioo_chatgpt_product_map_after', [
            'product' => $product,
            'mapped' => &$mapped,
            'store_id' => $storeId,
        ]);

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapFlatRow(Product $variant, ?Product $parent, int $storeId): array
    {
        $parent = $parent ?? $variant;
        $hasVariations = $parent->getTypeId() === Configurable::TYPE_CODE && $parent->getId() !== $variant->getId();
        $currency = $this->currency($storeId);
        $price = $this->finalPrice($variant);
        $listPrice = $this->regularPrice($variant);
        $availability = $this->availability($variant, $storeId);
        $search = $this->isSearchEligible($parent, $storeId);
        $checkout = $this->isCheckoutEligible($parent, $storeId);
        $images = $this->imageUrls($variant, $parent);
        $mainImage = $images[0] ?? '';
        $additional = array_slice($images, 1);

        $row = [
            'is_eligible_search' => $search ? 'true' : 'false',
            'is_eligible_checkout' => $checkout ? 'true' : 'false',
            'is_ads_eligible' => 'true',
            'item_id' => (string) $variant->getSku(),
            'gtin' => $this->gtin($variant, $storeId),
            'mpn' => $this->mpn($variant, $storeId),
            'title' => $this->clip((string) $variant->getName(), 150),
            'description' => $this->plainDescription($variant),
            'url' => $this->productUrl($hasVariations ? $parent : $variant, $storeId),
            'brand' => $this->clip($this->firstAttributeText($variant, $this->brandCodes($storeId), $parent), 70),
            'condition' => $this->condition($variant, $storeId),
            'product_category' => $this->categoryPath($parent, $storeId),
            'material' => $this->clip($this->firstAttributeText($variant, $this->materialCodes($storeId)), 100),
            'weight' => $variant->getWeight() ? (string) $variant->getWeight() : '',
            'item_weight_unit' => $variant->getWeight() ? $this->weightUnit() : '',
            'age_group' => strtolower($this->attributeText($variant, $this->config->getAgeGroupAttribute($storeId))),
            'image_url' => $mainImage,
            'additional_image_urls' => implode(',', $additional),
            'price' => $this->money->formatAmount($listPrice > 0 ? $listPrice : $price, $currency),
            'sale_price' => ($price > 0 && $listPrice > $price) ? $this->money->formatAmount($price, $currency) : '',
            'sale_price_start_date' => (string) $variant->getSpecialFromDate(),
            'sale_price_end_date' => (string) $variant->getSpecialToDate(),
            'availability' => $availability['status'],
            'group_id' => $hasVariations ? (string) $parent->getSku() : (string) $variant->getSku(),
            'listing_has_variations' => $hasVariations ? 'true' : 'false',
            'item_group_title' => $hasVariations ? $this->clip((string) $parent->getName(), 150) : '',
            'color' => $this->clip($this->firstAttributeText($variant, $this->colorCodes($storeId), $parent), 40),
            'size' => $this->clip($this->attributeText($variant, $this->config->getSizeAttribute($storeId)), 20),
            'gender' => strtolower($this->attributeText($variant, $this->config->getGenderAttribute($storeId))),
            'is_digital' => in_array($variant->getTypeId(), ['virtual', 'downloadable'], true) ? 'true' : 'false',
            'seller_name' => $this->config->getSellerName($storeId),
            'seller_url' => $this->config->getSellerUrl($storeId),
            'seller_privacy_policy' => $this->config->getPrivacyPolicyUrl($storeId),
            'seller_tos' => $this->config->getTermsUrl($storeId),
            'accepts_returns' => $this->config->acceptsReturns($storeId) ? 'true' : 'false',
            'return_deadline_in_days' => (string) $this->config->getReturnDeadlineDays($storeId),
            'accepts_exchanges' => $this->config->acceptsExchanges($storeId) ? 'true' : 'false',
            'return_policy' => $this->config->getReturnPolicyUrl($storeId),
            'target_countries' => $this->config->getTargetCountry($storeId),
            'store_country' => $this->config->getStoreCountry($storeId),
            'id' => (string) $variant->getSku(),
            'link' => $this->productUrl($hasVariations ? $parent : $variant, $storeId),
            'image_link' => $mainImage,
            'additional_image_link' => implode(',', $additional),
            'item_group_id' => $hasVariations ? (string) $parent->getSku() : '',
            'google_product_category' => $this->attributeText($parent, $this->config->getGoogleCategoryAttribute($storeId)),
            'product_type' => $this->categoryPath($parent, $storeId),
            'identifier_exists' => $this->gtin($variant, $storeId) !== '' || $this->mpn($variant, $storeId) !== '' ? 'yes' : 'no',
        ];

        $variantDict = $this->variantDict($variant, $storeId);
        if ($variantDict !== []) {
            $row['variant_dict'] = json_encode($variantDict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($this->config->includeReviews($storeId)) {
            $summary = $this->reviewSummary($parent, $storeId);
            $row['review_count'] = (string) $summary['count'];
            $row['star_rating'] = $summary['rating'] !== null ? number_format($summary['rating'], 2, '.', '') : '';
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapVariant(Product $variant, Product $parent, int $storeId): array
    {
        $currency = $this->currency($storeId);
        $price = $this->finalPrice($variant);
        $listPrice = $this->regularPrice($variant);
        $availability = $this->availability($variant, $storeId);
        $gtin = $this->gtin($variant, $storeId);
        $mpn = $this->mpn($variant, $storeId);
        $barcodes = [];
        if ($gtin !== '') {
            $barcodes[] = ['type' => 'gtin', 'value' => $gtin];
        }
        if ($mpn !== '') {
            $barcodes[] = ['type' => 'mpn', 'value' => $mpn];
        }

        $data = [
            'id' => (string) $variant->getSku(),
            'title' => $this->clip((string) $variant->getName(), 150),
            'description' => $this->mapDescription($variant),
            'url' => $this->productUrl($parent->getId() !== $variant->getId() ? $parent : $variant, $storeId),
            'barcodes' => $barcodes,
            'price' => ['amount' => $this->money->toMinorUnits($price, $currency), 'currency' => $currency],
            'availability' => $availability,
            'categories' => $this->categories($parent, $storeId),
            'condition' => [$this->condition($variant, $storeId)],
            'variant_options' => $this->variantOptions($variant, $storeId),
            'media' => $this->mapMedia($variant, $parent),
            'seller' => $this->seller($storeId),
        ];

        if ($listPrice > $price && $price > 0) {
            $data['list_price'] = ['amount' => $this->money->toMinorUnits($listPrice, $currency), 'currency' => $currency];
        }

        if ($this->config->includeReviews($storeId)) {
            $summary = $this->reviewSummary($parent, $storeId);
            if ($summary['count'] > 0) {
                $data['review_count'] = $summary['count'];
                $data['star_rating'] = $summary['rating'];
            }
        }

        return $data;
    }

    /**
     * @return array{plain: string, html?: string}
     */
    private function mapDescription(Product $product): array
    {
        $html = (string) ($product->getDescription() ?: $product->getShortDescription());
        $plain = $this->plainDescription($product);
        $result = ['plain' => $plain];
        if (trim(strip_tags($html)) !== '') {
            $result['html'] = $html;
        }
        return $result;
    }

    private function plainDescription(Product $product): string
    {
        $html = (string) ($product->getDescription() ?: $product->getShortDescription());
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
        return $this->clip($plain, 5000);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapMedia(Product $product, ?Product $fallback = null): array
    {
        $media = [];
        foreach ($this->imageUrls($product, $fallback) as $url) {
            $media[] = ['type' => 'image', 'url' => $url];
        }
        return $media;
    }

    /**
     * @return string[]
     */
    private function imageUrls(Product $product, ?Product $fallback = null): array
    {
        $urls = $this->galleryUrls($product);
        if ($urls === [] && $fallback && (int) $fallback->getId() !== (int) $product->getId()) {
            $urls = $this->galleryUrls($fallback);
        }
        if ($urls === []) {
            $file = (string) ($product->getImage() ?: ($fallback?->getImage() ?? ''));
            if ($file !== '' && $file !== 'no_selection') {
                $url = $this->imageHelper->init($fallback ?: $product, 'product_page_image_large')
                    ->setImageFile($file)
                    ->getUrl();
                if ($url) {
                    $urls[] = $url;
                }
            }
        }
        return array_values(array_unique(array_filter(
            array_map(fn (string $url): string => $this->rewriteMediaUrl($url, (int) $product->getStoreId()), $urls)
        )));
    }

    private function rewriteMediaUrl(string $url, int $storeId): string
    {
        $cdn = $this->config->getMediaCdnBaseUrl($storeId);
        if ($cdn === '' || $url === '') {
            return $url;
        }
        try {
            $mediaBase = rtrim(
                (string) $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
                '/'
            );
        } catch (\Throwable) {
            return $url;
        }
        if ($mediaBase !== '' && str_starts_with($url, $mediaBase)) {
            return $cdn . '/' . ltrim(substr($url, strlen($mediaBase)), '/');
        }
        return $url;
    }

    /**
     * @return string[]
     */
    private function galleryUrls(Product $product): array
    {
        try {
            $this->galleryReadHandler->execute($product);
        } catch (\Throwable) {
            return [];
        }
        $images = $product->getMediaGalleryImages();
        if (!$images) {
            return [];
        }
        $urls = [];
        foreach ($images as $image) {
            $url = (string) $image->getUrl();
            if ($url !== '') {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    /**
     * @return array{available: bool, status: string}
     */
    private function availability(Product $product, int $storeId): array
    {
        try {
            $stock = $this->stockRegistry->getStockItem((int) $product->getId(), (int) $this->storeManager->getStore($storeId)->getWebsiteId());
            $inStock = (bool) $stock->getIsInStock() && $stock->getQty() > 0;
            if ($stock->getManageStock() === false) {
                $inStock = (bool) $stock->getIsInStock();
            }
        } catch (\Throwable) {
            $inStock = true;
        }
        return [
            'available' => $inStock,
            'status' => $inStock ? 'in_stock' : 'out_of_stock',
        ];
    }

    /**
     * @return array<int, array{value: string, taxonomy?: string}>
     */
    private function categories(Product $product, int $storeId): array
    {
        $categories = [];
        $google = $this->attributeText($product, $this->config->getGoogleCategoryAttribute($storeId));
        if ($google !== '') {
            $categories[] = ['value' => $google, 'taxonomy' => 'google_product_category'];
        }
        $path = $this->categoryPath($product, $storeId);
        if ($path !== '') {
            $categories[] = ['value' => $path, 'taxonomy' => 'merchant'];
        }
        return $categories;
    }

    private function categoryPath(Product $product, int $storeId): string
    {
        try {
            $names = [];
            foreach ($product->getCategoryCollection()->addAttributeToSelect('name')->setStoreId($storeId) as $category) {
                if ((int) $category->getLevel() <= 1) {
                    continue;
                }
                $names[] = (string) $category->getName();
            }
            return implode(' > ', array_slice($names, 0, 5));
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    private function variantOptions(Product $product, int $storeId): array
    {
        $options = [];
        foreach ($this->variantDict($product, $storeId) as $name => $value) {
            $options[] = ['name' => $name, 'value' => $value];
        }
        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function variantDict(Product $product, int $storeId): array
    {
        $map = [
            'color' => $this->colorCodes($storeId),
            'size' => [$this->config->getSizeAttribute($storeId)],
            'material' => $this->materialCodes($storeId),
            'gender' => [$this->config->getGenderAttribute($storeId)],
            'age_group' => [$this->config->getAgeGroupAttribute($storeId)],
        ];
        $dict = [];
        foreach ($map as $key => $codes) {
            $value = $this->firstAttributeText($product, $codes);
            if ($value !== '') {
                $dict[$key] = $value;
            }
        }
        return $dict;
    }

    /**
     * @return array<string, mixed>
     */
    private function seller(int $storeId): array
    {
        $links = [];
        foreach ([
            'privacy_policy' => $this->config->getPrivacyPolicyUrl($storeId),
            'terms_of_service' => $this->config->getTermsUrl($storeId),
            'refund_policy' => $this->config->getReturnPolicyUrl($storeId),
            'shipping_policy' => $this->config->getShippingPolicyUrl($storeId),
            'faq' => $this->config->getFaqUrl($storeId),
        ] as $type => $url) {
            if ($url !== '') {
                $links[] = ['type' => $type, 'url' => $url];
            }
        }
        return [
            'name' => $this->config->getSellerName($storeId),
            'links' => $links,
        ];
    }

    private function productUrl(Product $product, int $storeId): string
    {
        try {
            $url = (string) $product->getProductUrl();
        } catch (\Throwable) {
            $url = $this->config->getStoreBaseUrl($storeId) . '/' . ltrim((string) $product->getUrlKey(), '/');
        }
        $query = array_filter([
            'utm_source' => $this->config->getUtmSource($storeId),
            'utm_medium' => $this->config->getUtmMedium($storeId),
            'utm_campaign' => $this->config->getUtmCampaign($storeId),
        ]);
        if ($query === []) {
            return $url;
        }
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    private function isSearchEligible(Product $product, int $storeId): bool
    {
        $value = $product->getData(AttributeCodes::SEARCH);
        if ($value === null || $value === '') {
            return $this->config->getDefaultSearchEligible($storeId);
        }
        return (int) $value === 1;
    }

    private function isCheckoutEligible(Product $product, int $storeId): bool
    {
        if (!$this->isSearchEligible($product, $storeId)) {
            return false;
        }
        $value = $product->getData(AttributeCodes::CHECKOUT);
        if ($value === null || $value === '') {
            return $this->config->getDefaultCheckoutEligible($storeId);
        }
        return (int) $value === 1;
    }

    private function gtin(Product $product, int $storeId): string
    {
        foreach ($this->gtinCodes($storeId) as $code) {
            $value = preg_replace('/[\s-]+/', '', (string) $product->getData($code)) ?? '';
            if ($value !== '' && preg_match('/^\d{8,14}$/', $value)) {
                return $value;
            }
        }
        return '';
    }

    /** @return string[] */
    private function gtinCodes(int $storeId): array
    {
        return $this->uniqueCodes([
            $this->config->getGtinAttribute($storeId),
            AttributeCodes::GTIN,
            'ean',
            'gtin',
        ]);
    }

    /** @return string[] */
    private function brandCodes(int $storeId): array
    {
        return $this->uniqueCodes([
            $this->config->getBrandAttribute($storeId),
            'manufacturer',
        ]);
    }

    /** @return string[] */
    private function colorCodes(int $storeId): array
    {
        return $this->uniqueCodes([
            $this->config->getColorAttribute($storeId),
            'color',
        ]);
    }

    /** @return string[] */
    private function materialCodes(int $storeId): array
    {
        return $this->uniqueCodes([
            $this->config->getMaterialAttribute($storeId),
            'material',
        ]);
    }

    /**
     * @param string[] $codes
     */
    private function firstAttributeText(Product $product, array $codes, ?Product $fallback = null): string
    {
        foreach ($codes as $code) {
            $value = $this->attributeText($product, $code, $fallback);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /**
     * @param string[] $codes
     * @return string[]
     */
    private function uniqueCodes(array $codes): array
    {
        $out = [];
        foreach ($codes as $code) {
            $code = trim((string) $code);
            if ($code !== '' && !in_array($code, $out, true)) {
                $out[] = $code;
            }
        }
        return $out;
    }

    private function mpn(Product $product, int $storeId): string
    {
        foreach ([$this->config->getMpnAttribute($storeId), AttributeCodes::MPN] as $code) {
            $value = trim((string) $product->getData($code));
            if ($value !== '') {
                return $this->clip($value, 70);
            }
        }
        return '';
    }

    private function condition(Product $product, int $storeId): string
    {
        $code = $this->config->getConditionAttribute($storeId);
        $value = strtolower($this->attributeText($product, $code));
        $allowed = ['new', 'refurbished', 'used', 'secondhand'];
        return in_array($value, $allowed, true) ? $value : $this->config->getDefaultCondition($storeId);
    }

    private function attributeText(Product $product, string $code, ?Product $fallback = null): string
    {
        if ($code === '') {
            return '';
        }
        try {
            $text = (string) $product->getAttributeText($code);
            if ($text !== '') {
                return is_array($product->getAttributeText($code))
                    ? implode(', ', (array) $product->getAttributeText($code))
                    : $text;
            }
        } catch (\Throwable) {
        }
        $raw = trim((string) $product->getData($code));
        if ($raw !== '') {
            return $raw;
        }
        if ($fallback && (int) $fallback->getId() !== (int) $product->getId()) {
            return $this->attributeText($fallback, $code);
        }
        return '';
    }

    private function finalPrice(Product $product): float
    {
        try {
            $value = (float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
            if ($value > 0) {
                return $value;
            }
        } catch (\Throwable) {
        }
        return (float) ($product->getFinalPrice() ?: $product->getPrice());
    }

    private function regularPrice(Product $product): float
    {
        try {
            $value = (float) $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
            if ($value > 0) {
                return $value;
            }
        } catch (\Throwable) {
        }
        return (float) $product->getPrice();
    }

    private function currency(int $storeId): string
    {
        try {
            return strtoupper((string) $this->storeManager->getStore($storeId)->getCurrentCurrencyCode());
        } catch (\Throwable) {
            return 'EUR';
        }
    }

    private function weightUnit(): string
    {
        $unit = (string) $this->storeManager->getStore()->getConfig('general/locale/weight_unit');
        return $unit === 'lbs' ? 'lb' : 'kg';
    }

    /**
     * @return array{count: int, rating: float|null}
     */
    private function reviewSummary(Product $product, int $storeId): array
    {
        try {
            $this->reviewFactory->create()->getEntitySummary($product, $storeId);
            $summary = $product->getRatingSummary();
            if (!$summary) {
                return ['count' => 0, 'rating' => null];
            }
            $count = (int) $summary->getReviewsCount();
            $percent = (float) $summary->getRatingSummary();
            return [
                'count' => $count,
                'rating' => $count > 0 ? round($percent / 20, 2) : null,
            ];
        } catch (\Throwable) {
            return ['count' => 0, 'rating' => null];
        }
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return $this->string->strlen($value) > $max
            ? (string) $this->string->substr($value, 0, $max)
            : $value;
    }
}
