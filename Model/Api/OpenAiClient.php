<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Api;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Math\Random;
use Solutioo\ChatGptProductSearch\Model\Config;

class OpenAiClient
{
    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly Random $random
    ) {
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request(int $storeId, string $method, string $path, ?array $body = null): array
    {
        $apiKey = $this->config->getApiKey($storeId);
        if ($apiKey === '') {
            throw new \RuntimeException((string) __('OpenAI API-Key fehlt.'));
        }

        $url = $this->config->getApiBaseUrl($storeId) . '/' . ltrim($path, '/');
        $curl = $this->curlFactory->create();
        $headers = $this->headers($storeId, $apiKey);
        $curl->setHeaders($headers);
        $curl->setTimeout(60);
        $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $this->dispatch($curl, strtoupper($method), $url, $payload);

        $status = (int) $curl->getStatus();
        $raw = (string) $curl->getBody();
        $decoded = json_decode($raw, true);
        if ($status >= 400) {
            $message = is_array($decoded)
                ? (string) ($decoded['error']['message'] ?? $decoded['message'] ?? $raw)
                : $raw;
            throw new \RuntimeException(sprintf('OpenAI API %d: %s', $status, $message));
        }

        return is_array($decoded) ? $decoded : ['_raw' => $raw, 'status' => $status];
    }

    /**
     * @return array<string, mixed>
     */
    public function createFeed(int $storeId): array
    {
        if ($this->config->isAdsApi($storeId)) {
            throw new \RuntimeException(
                (string) __('Ads-Feeds werden im OpenAI Ads Manager angelegt, nicht per API.')
            );
        }
        return $this->request($storeId, 'POST', '/product_feeds', [
            'target_country' => $this->config->getTargetCountry($storeId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFeed(int $storeId, string $feedId): array
    {
        if ($this->config->isAdsApi($storeId)) {
            $account = $this->getAdAccount($storeId);
            return [
                'id' => $feedId,
                'account_id' => (string) ($account['id'] ?? ''),
                'name' => (string) ($account['name'] ?? ''),
                'url' => (string) ($account['url'] ?? ''),
                'target_country' => $this->config->getTargetCountry($storeId),
                'account' => $account,
            ];
        }
        return $this->request($storeId, 'GET', '/product_feeds/' . rawurlencode($feedId));
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdAccount(int $storeId): array
    {
        return $this->request($storeId, 'GET', '/ad_account');
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<string, mixed>
     */
    public function upsertProducts(int $storeId, string $feedId, array $products): array
    {
        if ($this->config->isAdsApi($storeId)) {
            return $this->request($storeId, 'PATCH', '/feeds/' . rawurlencode($feedId) . '/products', [
                'products' => $products,
            ]);
        }
        return $this->request($storeId, 'PATCH', '/product_feeds/' . rawurlencode($feedId) . '/products', [
            'target_country' => $this->config->getTargetCountry($storeId),
            'products' => $products,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $promotions
     * @return array<string, mixed>
     */
    public function upsertPromotions(int $storeId, string $feedId, array $promotions): array
    {
        if ($this->config->isAdsApi($storeId)) {
            return ['skipped' => true, 'reason' => 'Ads-API unterstützt keine Promotions-Upserts.'];
        }
        return $this->request($storeId, 'PATCH', '/product_feeds/' . rawurlencode($feedId) . '/promotions', $promotions);
    }

    /**
     * @return array<string, string>
     */
    private function headers(int $storeId, string $apiKey): array
    {
        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept-Language' => 'de-DE',
            'User-Agent' => 'Solutioo-ChatGptProductSearch/1.0 (Magento)',
            'Idempotency-Key' => $this->random->getUniqueHash(),
            'Request-Id' => $this->random->getUniqueHash(),
            'Content-Type' => 'application/json',
            'Timestamp' => gmdate('c'),
            'API-Version' => $this->config->getApiVersion($storeId),
        ];
    }

    private function dispatch(Curl $curl, string $method, string $url, ?string $payload): void
    {
        match ($method) {
            'POST' => $curl->post($url, $payload ?? ''),
            'GET' => $curl->get($url),
            default => $this->custom($curl, $method, $url, $payload),
        };
    }

    private function custom(Curl $curl, string $method, string $url, ?string $payload): void
    {
        $curl->setOption(CURLOPT_CUSTOMREQUEST, $method);
        if ($payload !== null) {
            $curl->setOption(CURLOPT_POSTFIELDS, $payload);
        }
        $curl->get($url);
    }
}
