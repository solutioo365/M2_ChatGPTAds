<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\Base\Model\ConfigProviderAbstract;

class Config extends ConfigProviderAbstract
{
    public const XML_SECTION = 'solutioo_chatgpt';

    protected $pathPrefix = 'solutioo_chatgpt/';

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($scopeConfig);
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag('general/enabled', $storeId);
    }

    public function getSellerName(?int $storeId = null): string
    {
        $name = trim((string) $this->getValue('seller/name', $storeId));
        if ($name !== '') {
            return mb_substr($name, 0, 70);
        }
        try {
            return mb_substr((string) $this->storeManager->getStore($storeId)->getFrontendName(), 0, 70);
        } catch (\Throwable) {
            return 'Store';
        }
    }

    public function getSellerUrl(?int $storeId = null): string
    {
        $url = trim((string) $this->getValue('seller/url', $storeId));
        return $url !== '' ? $url : $this->getStoreBaseUrl($storeId);
    }

    public function getPrivacyPolicyUrl(?int $storeId = null): string
    {
        return trim((string) $this->getValue('seller/privacy_url', $storeId));
    }

    public function getTermsUrl(?int $storeId = null): string
    {
        return trim((string) $this->getValue('seller/tos_url', $storeId));
    }

    public function getReturnPolicyUrl(?int $storeId = null): string
    {
        return trim((string) $this->getValue('seller/return_url', $storeId));
    }

    public function getShippingPolicyUrl(?int $storeId = null): string
    {
        return trim((string) $this->getValue('seller/shipping_url', $storeId));
    }

    public function getFaqUrl(?int $storeId = null): string
    {
        return trim((string) $this->getValue('seller/faq_url', $storeId));
    }

    public function acceptsReturns(?int $storeId = null): bool
    {
        return $this->isSetFlag('seller/accepts_returns', $storeId);
    }

    public function getReturnDeadlineDays(?int $storeId = null): int
    {
        return max(0, (int) $this->getValue('seller/return_deadline_days', $storeId));
    }

    public function acceptsExchanges(?int $storeId = null): bool
    {
        return $this->isSetFlag('seller/accepts_exchanges', $storeId);
    }

    public function getTargetCountry(?int $storeId = null): string
    {
        $country = strtoupper(trim((string) $this->getValue('seller/target_country', $storeId)));
        if ($country !== '') {
            return $country;
        }
        return strtoupper((string) $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getStoreCountry(?int $storeId = null): string
    {
        $country = strtoupper(trim((string) $this->getValue('seller/store_country', $storeId)));
        return $country !== '' ? $country : $this->getTargetCountry($storeId);
    }

    public function getFeedFormat(?int $storeId = null): string
    {
        $format = (string) $this->getValue('feed/format', $storeId);
        return $format !== '' ? $format : 'jsonl';
    }

    public function compressOutput(?int $storeId = null): bool
    {
        return $this->isSetFlag('feed/gzip', $storeId);
    }

    public function includeOutOfStock(?int $storeId = null): bool
    {
        return $this->isSetFlag('feed/include_out_of_stock', $storeId);
    }

    public function includeDisabledProducts(?int $storeId = null): bool
    {
        return $this->isSetFlag('feed/include_disabled', $storeId);
    }

    public function includeDisabled(?int $storeId = null): bool
    {
        return $this->includeDisabledProducts($storeId);
    }

    public function onlyEligibleSearch(?int $storeId = null): bool
    {
        return $this->isSetFlag('feed/only_eligible_search', $storeId);
    }

    public function getVariantMode(?int $storeId = null): string
    {
        $mode = (string) $this->getValue('feed/variant_mode', $storeId);
        return $mode !== '' ? $mode : 'all';
    }

    public function exportParentsOnly(?int $storeId = null): bool
    {
        return $this->getVariantMode($storeId) === 'parents';
    }

    public function excludeIncomplete(?int $storeId = null): bool
    {
        return $this->isSetFlag('feed/exclude_incomplete', $storeId);
    }

    public function getMediaCdnBaseUrl(?int $storeId = null): string
    {
        return rtrim(trim((string) $this->getValue('feed/media_cdn_url', $storeId)), '/');
    }

    /** @return int[] */
    public function getVisibilityIds(?int $storeId = null): array
    {
        $raw = (string) $this->getValue('feed/visibility', $storeId);
        if ($raw === '') {
            return [2, 3, 4];
        }
        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    /** @return int[] */
    public function getIncludeCategoryIds(?int $storeId = null): array
    {
        return $this->splitIds((string) $this->getValue('feed/include_categories', $storeId));
    }

    /** @return int[] */
    public function getExcludeCategoryIds(?int $storeId = null): array
    {
        return $this->splitIds((string) $this->getValue('feed/exclude_categories', $storeId));
    }

    public function getSampleLimit(?int $storeId = null): int
    {
        return max(0, (int) $this->getValue('feed/sample_limit', $storeId));
    }

    public function getDefaultSearchEligible(?int $storeId = null): bool
    {
        return $this->isSetFlag('feed/default_search_eligible', $storeId);
    }

    public function getDefaultCheckoutEligible(?int $storeId = null): bool
    {
        return $this->isSetFlag('feed/default_checkout_eligible', $storeId);
    }

    public function getUtmSource(?int $storeId = null): string
    {
        return trim((string) $this->getValue('feed/utm_source', $storeId));
    }

    public function getUtmMedium(?int $storeId = null): string
    {
        return trim((string) $this->getValue('feed/utm_medium', $storeId));
    }

    public function getUtmCampaign(?int $storeId = null): string
    {
        return trim((string) $this->getValue('feed/utm_campaign', $storeId));
    }

    public function getBrandAttribute(?int $storeId = null): string
    {
        $code = trim((string) $this->getValue('mapping/brand_attribute', $storeId));
        return $code !== '' ? $code : 'manufacturer';
    }

    public function getGtinAttribute(?int $storeId = null): string
    {
        $code = trim((string) $this->getValue('mapping/gtin_attribute', $storeId));
        return $code !== '' ? $code : AttributeCodes::GTIN;
    }

    public function getMpnAttribute(?int $storeId = null): string
    {
        $code = trim((string) $this->getValue('mapping/mpn_attribute', $storeId));
        return $code !== '' ? $code : AttributeCodes::MPN;
    }

    public function getConditionAttribute(?int $storeId = null): string
    {
        return trim((string) $this->getValue('mapping/condition_attribute', $storeId));
    }

    public function getDefaultCondition(?int $storeId = null): string
    {
        $value = trim((string) $this->getValue('mapping/default_condition', $storeId));
        return $value !== '' ? $value : 'new';
    }

    public function getGoogleCategoryAttribute(?int $storeId = null): string
    {
        return trim((string) $this->getValue('mapping/google_category_attribute', $storeId));
    }

    public function getColorAttribute(?int $storeId = null): string
    {
        $code = trim((string) $this->getValue('mapping/color_attribute', $storeId));
        return $code !== '' ? $code : 'color';
    }

    public function getSizeAttribute(?int $storeId = null): string
    {
        $code = trim((string) $this->getValue('mapping/size_attribute', $storeId));
        return $code !== '' ? $code : 'size';
    }

    public function getMaterialAttribute(?int $storeId = null): string
    {
        $code = trim((string) $this->getValue('mapping/material_attribute', $storeId));
        return $code !== '' ? $code : 'material';
    }

    public function getGenderAttribute(?int $storeId = null): string
    {
        return trim((string) $this->getValue('mapping/gender_attribute', $storeId));
    }

    public function getAgeGroupAttribute(?int $storeId = null): string
    {
        return trim((string) $this->getValue('mapping/age_group_attribute', $storeId));
    }

    public function includeReviews(?int $storeId = null): bool
    {
        return $this->isSetFlag('mapping/include_reviews', $storeId);
    }

    public function includePromotions(?int $storeId = null): bool
    {
        return $this->isSetFlag('feed/include_promotions', $storeId);
    }

    public function getHttpsEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag('delivery/https_enabled', $storeId);
    }

    public function getFeedToken(?int $storeId = null): string
    {
        return $this->decryptValue((string) $this->getValue('delivery/feed_token', $storeId));
    }

    public function generateOnRequest(?int $storeId = null): bool
    {
        return $this->isSetFlag('delivery/generate_on_request', $storeId);
    }

    public function isSftpEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag('sftp/enabled', $storeId);
    }

    public function getSftpHost(?int $storeId = null): string
    {
        return trim((string) $this->getValue('sftp/host', $storeId));
    }

    public function getSftpPort(?int $storeId = null): int
    {
        $port = (int) $this->getValue('sftp/port', $storeId);
        return $port > 0 ? $port : 22;
    }

    public function getSftpUsername(?int $storeId = null): string
    {
        return trim((string) $this->getValue('sftp/username', $storeId));
    }

    public function getSftpPassword(?int $storeId = null): string
    {
        return $this->decryptValue((string) $this->getValue('sftp/password', $storeId));
    }

    public function getSftpPrivateKey(?int $storeId = null): string
    {
        return trim((string) $this->getValue('sftp/private_key', $storeId));
    }

    public function getSftpRemotePath(?int $storeId = null): string
    {
        $path = trim((string) $this->getValue('sftp/remote_path', $storeId));
        $path = $path !== '' ? rtrim($path, '/') : '';
        return $path !== '' ? $path : '/';
    }

    public function getSftpFilename(?int $storeId = null): string
    {
        $name = trim((string) $this->getValue('sftp/filename', $storeId));
        return $name !== '' ? $name : 'products.jsonl.gz';
    }

    public function isApiEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag('api/enabled', $storeId);
    }

    public function getApiMode(?int $storeId = null): string
    {
        $mode = strtolower(trim((string) $this->getValue('api/mode', $storeId)));
        return $mode === 'commerce' ? 'commerce' : 'ads';
    }

    public function isAdsApi(?int $storeId = null): bool
    {
        return $this->getApiMode($storeId) === 'ads';
    }

    public function getApiBaseUrl(?int $storeId = null): string
    {
        $url = rtrim(trim((string) $this->getValue('api/base_url', $storeId)), '/');
        if ($url !== '') {
            return $url;
        }
        return $this->isAdsApi($storeId) ? 'https://api.ads.openai.com/v1' : 'https://api.openai.com/v1';
    }

    public function getApiKey(?int $storeId = null): string
    {
        return $this->decryptValue((string) $this->getValue('api/api_key', $storeId));
    }

    public function getApiVersion(?int $storeId = null): string
    {
        $version = trim((string) $this->getValue('api/version', $storeId));
        return $version !== '' ? $version : '2025-09-12';
    }

    public function getOpenAiFeedId(?int $storeId = null): string
    {
        return trim((string) $this->getValue('api/feed_id', $storeId));
    }

    public function getOpenAiAccountId(?int $storeId = null): string
    {
        return trim((string) $this->getValue('api/account_id', $storeId));
    }

    public function getOpenAiMerchantId(?int $storeId = null): string
    {
        return trim((string) $this->getValue('api/merchant_id', $storeId));
    }

    public function getApiBatchSize(?int $storeId = null): int
    {
        $size = (int) $this->getValue('api/batch_size', $storeId);
        return $size > 0 ? $size : 100;
    }

    public function isCronEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag('schedule/enabled', $storeId);
    }

    public function getCronExpr(?int $storeId = null): string
    {
        $expr = trim((string) $this->getValue('schedule/cron_expr', $storeId));
        return $expr !== '' ? $expr : '0 3 * * *';
    }

    public function autoUploadSftp(?int $storeId = null): bool
    {
        return $this->isSetFlag('schedule/auto_sftp', $storeId);
    }

    public function autoSyncApi(?int $storeId = null): bool
    {
        return $this->isSetFlag('schedule/auto_api', $storeId);
    }

    public function isTrackingEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag('tracking/enabled', $storeId) && $this->getPixelId($storeId) !== '';
    }

    public function getPixelId(?int $storeId = null): string
    {
        return trim((string) $this->getValue('tracking/pixel_id', $storeId));
    }

    public function isPixelDebug(?int $storeId = null): bool
    {
        return $this->isSetFlag('tracking/debug', $storeId);
    }

    public function respectCookiebot(?int $storeId = null): bool
    {
        return $this->isSetFlag('tracking/respect_cookiebot', $storeId);
    }

    public function getCookiebotCategory(?int $storeId = null): string
    {
        $category = trim((string) $this->getValue('tracking/cookiebot_category', $storeId));
        return $category !== '' ? $category : 'marketing';
    }

    public function trackPageView(?int $storeId = null): bool
    {
        return $this->isSetFlag('tracking/track_page_view', $storeId);
    }

    public function trackProductView(?int $storeId = null): bool
    {
        return $this->isSetFlag('tracking/track_product_view', $storeId);
    }

    public function trackAddToCart(?int $storeId = null): bool
    {
        return $this->isSetFlag('tracking/track_add_to_cart', $storeId);
    }

    public function trackCheckout(?int $storeId = null): bool
    {
        return $this->isSetFlag('tracking/track_checkout', $storeId);
    }

    public function trackPurchase(?int $storeId = null): bool
    {
        return $this->isSetFlag('tracking/track_purchase', $storeId);
    }

    public function getPublicFeedUrl(?int $storeId = null): string
    {
        $token = $this->getFeedToken($storeId);
        $base = $this->getStoreBaseUrl($storeId);
        $params = http_build_query(array_filter([
            'token' => $token,
            'store' => $storeId,
        ], static fn ($v) => $v !== null && $v !== ''));
        return rtrim($base, '/') . '/chatgpt/feed' . ($params !== '' ? '?' . $params : '');
    }

    public function getStoreBaseUrl(?int $storeId = null): string
    {
        try {
            $store = $storeId !== null
                ? $this->storeManager->getStore($storeId)
                : $this->storeManager->getStore();
            return rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_WEB), '/');
        } catch (\Throwable) {
            return '';
        }
    }

    /** @return int[] */
    private function splitIds(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s]+/', $raw) ?: []))));
    }

    private function decryptValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        try {
            $decrypted = $this->encryptor->decrypt($value);
            return $decrypted !== '' ? $decrypted : $value;
        } catch (\Throwable) {
            return $value;
        }
    }
}
