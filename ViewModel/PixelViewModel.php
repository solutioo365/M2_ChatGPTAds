<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Model\Config;

class PixelViewModel implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpRequest $request,
        private readonly Registry $registry,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isTrackingEnabled($this->getStoreId());
    }

    public function getPixelId(): string
    {
        return $this->config->getPixelId($this->getStoreId());
    }

    public function useConsentGating(): bool
    {
        return $this->config->respectCookiebot($this->getStoreId());
    }

    public function getCookiebotCategory(): string
    {
        return $this->config->getCookiebotCategory($this->getStoreId());
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsConfig(): array
    {
        $storeId = $this->getStoreId();
        $currency = $this->getCurrencyCode();

        $config = [
            'pixelId' => $this->getPixelId(),
            'debug' => $this->config->isPixelDebug($storeId),
            'respectCookiebot' => $this->useConsentGating(),
            'cookiebotCategory' => $this->getCookiebotCategory(),
            'currency' => $currency,
            'events' => [
                'page_viewed' => $this->wrapEvent($this->config->trackPageView($storeId) ? $this->pageViewedPayload() : null),
                'contents_viewed' => $this->wrapEvent($this->config->trackProductView($storeId) ? $this->contentsViewedPayload() : null),
                'checkout_started' => $this->wrapEvent($this->config->trackCheckout($storeId) ? $this->checkoutStartedPayload() : null),
                'order_created' => $this->wrapEvent($this->config->trackPurchase($storeId) ? $this->orderCreatedPayload() : null),
            ],
            'trackAddToCart' => $this->config->trackAddToCart($storeId),
        ];
        $user = $this->matchingUser();
        if ($user !== null) {
            $config['user'] = $user;
        }

        return $config;
    }

    public function jsonEncode(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageViewedPayload(): array
    {
        $action = $this->getFullActionName();
        return [
            'type' => 'contents',
            'contents' => [[
                'id' => $action !== '' ? $action : 'page',
                'name' => $action !== '' ? $action : 'page',
                'content_type' => 'page',
            ]],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contentsViewedPayload(): ?array
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return null;
        }
        $currency = $this->getCurrencyCode();
        $amount = $this->toMinorUnits((float) $product->getFinalPrice(), $currency);

        return [
            'type' => 'contents',
            'amount' => $amount,
            'currency' => $currency,
            'contents' => [[
                'id' => (string) $product->getSku(),
                'name' => (string) $product->getName(),
                'content_type' => 'product',
                'quantity' => 1,
                'amount' => $amount,
                'currency' => $currency,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function checkoutStartedPayload(): ?array
    {
        if ($this->getFullActionName() !== 'checkout_index_index') {
            return null;
        }
        try {
            $quote = $this->checkoutSession->getQuote();
        } catch (\Throwable) {
            return null;
        }
        if (!$quote || !$quote->getId()) {
            return null;
        }

        return $this->contentsFromItems(
            $quote->getAllVisibleItems(),
            (float) $quote->getGrandTotal(),
            (string) ($quote->getQuoteCurrencyCode() ?: $this->getCurrencyCode())
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function orderCreatedPayload(): ?array
    {
        if ($this->getFullActionName() !== 'checkout_onepage_success') {
            return null;
        }
        try {
            $order = $this->checkoutSession->getLastRealOrder();
        } catch (\Throwable) {
            return null;
        }
        if (!$order || !$order->getId()) {
            return null;
        }

        $payload = $this->contentsFromItems(
            $order->getAllVisibleItems(),
            (float) $order->getGrandTotal(),
            (string) ($order->getOrderCurrencyCode() ?: $this->getCurrencyCode())
        );
        if ($payload === null) {
            return null;
        }
        $payload['_event_id'] = 'order_' . (string) $order->getIncrementId();

        return $payload;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{data: array<string, mixed>, options: array<string, mixed>}|null
     */
    private function wrapEvent(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }
        $options = [];
        if (isset($payload['_event_id'])) {
            $options['event_id'] = (string) $payload['_event_id'];
            unset($payload['_event_id']);
        }
        return [
            'data' => $payload,
            'options' => $options,
        ];
    }

    /**
     * @param QuoteItem[]|OrderItem[] $items
     * @return array<string, mixed>|null
     */
    private function contentsFromItems(array $items, float $grandTotal, string $currency): ?array
    {
        $contents = [];
        foreach ($items as $item) {
            $rawQty = $item instanceof OrderItem ? $item->getQtyOrdered() : $item->getQty();
            $qty = (int) max(1, (float) $rawQty);
            $rowTotal = (float) $item->getRowTotalInclTax();
            if ($rowTotal <= 0.0) {
                $rowTotal = (float) $item->getRowTotal();
            }
            $amount = $this->toMinorUnits($rowTotal, $currency);
            $contents[] = [
                'id' => (string) $item->getSku(),
                'name' => (string) $item->getName(),
                'content_type' => 'product',
                'quantity' => $qty,
                'amount' => $amount,
                'currency' => $currency,
            ];
        }
        if ($contents === []) {
            return null;
        }

        return [
            'type' => 'contents',
            'amount' => $this->toMinorUnits($grandTotal, $currency),
            'currency' => $currency,
            'contents' => $contents,
        ];
    }

    /**
     * Gehashte Bestelldaten für das Measurement-Pixel. Rohwerte verlassen den Server nicht.
     *
     * @return array<string, string>|null
     */
    private function matchingUser(): ?array
    {
        if ($this->getFullActionName() !== 'checkout_onepage_success') {
            return null;
        }
        try {
            $order = $this->checkoutSession->getLastRealOrder();
        } catch (\Throwable) {
            return null;
        }
        if (!$order || !$order->getId()) {
            return null;
        }

        $address = $order->getBillingAddress();
        if (!is_object($address)) {
            $address = null;
        }
        $country = strtoupper(trim((string) ($address?->getCountryId() ?? '')));
        $user = [];

        $email = mb_strtolower(trim((string) $order->getCustomerEmail()));
        if ($email !== '' && str_contains($email, '@')) {
            $user['email_sha256'] = hash('sha256', $email);
        }

        $phone = $this->normalizePhone((string) ($address?->getTelephone() ?? ''), $country);
        if ($phone !== '') {
            $user['phone_number_sha256'] = hash('sha256', $phone);
        }

        $customerId = (int) $order->getCustomerId();
        if ($customerId > 0) {
            $user['external_id_sha256'] = hash('sha256', (string) $customerId);
        }

        $first = $this->normalizePersonName((string) ($order->getCustomerFirstname() ?: $address?->getFirstname()));
        $last = $this->normalizePersonName((string) ($order->getCustomerLastname() ?: $address?->getLastname()));
        if ($first !== '') {
            $user['first_name_sha256'] = hash('sha256', $first);
        }
        if ($last !== '') {
            $user['last_name_sha256'] = hash('sha256', $last);
        }

        if (preg_match('/^[A-Z]{2}$/', $country)) {
            $user['country'] = $country;
        }
        $city = mb_substr(trim((string) ($address?->getCity() ?? '')), 0, 128);
        if ($city !== '') {
            $user['city'] = $city;
        }
        $region = mb_substr(trim((string) ($address?->getRegion() ?? '')), 0, 128);
        if ($region !== '') {
            $user['region'] = $region;
        }
        $postcode = mb_substr(trim((string) ($address?->getPostcode() ?? '')), 0, 32);
        if ($postcode !== '' && preg_match('/^[A-Za-z0-9 \-]+$/', $postcode)) {
            $user['postal_code'] = $postcode;
        }

        return $user === [] ? null : $user;
    }

    private function normalizePhone(string $phone, string $country): string
    {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            return '';
        }
        $hadPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (!$hadPlus && str_starts_with($digits, '0') && $country === 'DE') {
            $digits = '49' . ltrim($digits, '0');
        } else {
            $digits = ltrim($digits, '0');
        }

        return preg_match('/^\d{8,15}$/', $digits) ? $digits : '';
    }

    private function normalizePersonName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return '';
        }

        return preg_replace('/[\s!"#$%&\'()*+,\-.\/:;<=>?@\[\]^_`{|}~]+/u', '', $name) ?? '';
    }

    private function getCurrentProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product ? $product : null;
    }

    private function getFullActionName(): string
    {
        return strtolower(sprintf(
            '%s_%s_%s',
            (string) $this->request->getRouteName(),
            (string) $this->request->getControllerName(),
            (string) $this->request->getActionName()
        ));
    }

    private function getCurrencyCode(): string
    {
        try {
            $code = (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
            return $code !== '' ? $code : 'EUR';
        } catch (\Throwable) {
            return 'EUR';
        }
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        $zeroDecimal = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        $factor = in_array(strtoupper($currency), $zeroDecimal, true) ? 1 : 100;
        return (int) round($amount * $factor);
    }

    private function getStoreId(): ?int
    {
        try {
            return (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable) {
            return null;
        }
    }
}
