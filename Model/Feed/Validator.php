<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Feed;

use Solutioo\ChatGptProductSearch\Model\Config;

class Validator
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array{errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>, valid: int, invalid: int}
     */
    public function validateProducts(array $products, int $storeId): array
    {
        $errors = [];
        $warnings = [];
        $valid = 0;
        $invalid = 0;

        foreach ($products as $product) {
            $id = (string) ($product['id'] ?? '');
            $issues = $this->validateProduct($product, $storeId);
            $hasError = false;
            foreach ($issues as $issue) {
                $issue['product_id'] = $id;
                if ($issue['level'] === 'error') {
                    $errors[] = $issue;
                    $hasError = true;
                } else {
                    $warnings[] = $issue;
                }
            }
            if ($hasError) {
                $invalid++;
            } else {
                $valid++;
            }
        }

        $this->validateSeller($storeId, $warnings);

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'valid' => $valid,
            'invalid' => $invalid,
        ];
    }

    /**
     * @param array<string, mixed> $product
     * @return array<int, array{level: string, field: string, message: string}>
     */
    private function validateProduct(array $product, int $storeId): array
    {
        $issues = [];
        if (trim((string) ($product['id'] ?? '')) === '') {
            $issues[] = $this->issue('error', 'id', 'Produkt-ID / SKU fehlt.');
        }
        if (trim((string) ($product['title'] ?? '')) === '') {
            $issues[] = $this->issue('error', 'title', 'Titel fehlt.');
        }
        $plain = (string) ($product['description']['plain'] ?? '');
        if (trim($plain) === '') {
            $issues[] = $this->issue('error', 'description', 'Beschreibung fehlt.');
        }
        $url = (string) ($product['url'] ?? '');
        if (!$this->isHttpUrl($url)) {
            $issues[] = $this->issue('error', 'url', 'Produkt-URL ungültig oder nicht HTTP(S).');
        }
        $variants = $product['variants'] ?? [];
        if (!is_array($variants) || $variants === []) {
            $issues[] = $this->issue('error', 'variants', 'Mindestens eine Variante ist erforderlich.');
            return $issues;
        }

        $hasImage = $this->hasMedia($product['media'] ?? []);
        foreach ($variants as $index => $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $prefix = 'variants[' . $index . ']';
            if (trim((string) ($variant['id'] ?? '')) === '') {
                $issues[] = $this->issue('error', $prefix . '.id', 'Varianten-ID fehlt.');
            }
            if (trim((string) ($variant['title'] ?? '')) === '') {
                $issues[] = $this->issue('error', $prefix . '.title', 'Variantentitel fehlt.');
            }
            $amount = (int) ($variant['price']['amount'] ?? 0);
            $currency = (string) ($variant['price']['currency'] ?? '');
            if ($amount <= 0) {
                $issues[] = $this->issue('error', $prefix . '.price', 'Preis muss größer 0 sein.');
            }
            if (strlen($currency) !== 3) {
                $issues[] = $this->issue('error', $prefix . '.price.currency', 'Währung muss ISO-4217 (3 Zeichen) sein.');
            }
            if ($this->hasMedia($variant['media'] ?? [])) {
                $hasImage = true;
            }
            $barcodes = $variant['barcodes'] ?? [];
            $hasId = false;
            if (is_array($barcodes)) {
                foreach ($barcodes as $barcode) {
                    if (!empty($barcode['value'])) {
                        $hasId = true;
                        break;
                    }
                }
            }
            if (!$hasId) {
                $issues[] = $this->issue('warning', $prefix . '.barcodes', 'Weder GTIN noch MPN gesetzt (identifier_exists=no).');
            }
        }

        if (!$hasImage) {
            $issues[] = $this->issue('error', 'media', 'Mindestens ein Produktbild (HTTPS) wird benötigt.');
        }

        if (($product['is_eligible_checkout'] ?? false) && $this->config->getPrivacyPolicyUrl($storeId) === '') {
            $issues[] = $this->issue('warning', 'seller.privacy_policy', 'Checkout-fähig, aber keine Datenschutz-URL konfiguriert.');
        }

        return $issues;
    }

    /**
     * @param array<int, array<string, mixed>> $warnings
     */
    private function validateSeller(int $storeId, array &$warnings): void
    {
        if ($this->config->getSellerName($storeId) === '') {
            $warnings[] = $this->issue('warning', 'seller.name', 'Händlername ist leer.') + ['product_id' => ''];
        }
        if (!$this->isHttpUrl($this->config->getSellerUrl($storeId))) {
            $warnings[] = $this->issue('warning', 'seller.url', 'Händler-URL fehlt oder ist ungültig.') + ['product_id' => ''];
        }
        if ($this->config->getReturnPolicyUrl($storeId) === '') {
            $warnings[] = $this->issue('warning', 'return_policy', 'Rückgabe-URL fehlt (im Flat-Feed required).') + ['product_id' => ''];
        }
        $links = 0;
        foreach ([
            $this->config->getPrivacyPolicyUrl($storeId),
            $this->config->getTermsUrl($storeId),
            $this->config->getReturnPolicyUrl($storeId),
            $this->config->getShippingPolicyUrl($storeId),
        ] as $url) {
            if ($url !== '') {
                $links++;
            }
        }
        if ($links < 2) {
            $warnings[] = $this->issue(
                'warning',
                'seller.links',
                'OpenAI empfiehlt mindestens zwei Policy-Links (Datenschutz, AGB, Rückgabe, Versand).'
            ) + ['product_id' => ''];
        }
    }

    /**
     * @return array{level: string, field: string, message: string}
     */
    private function issue(string $level, string $field, string $message): array
    {
        return ['level' => $level, 'field' => $field, 'message' => $message];
    }

    private function isHttpUrl(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', $url);
    }

    private function hasMedia(mixed $media): bool
    {
        if (!is_array($media)) {
            return false;
        }
        foreach ($media as $item) {
            if (is_array($item) && $this->isHttpUrl((string) ($item['url'] ?? ''))) {
                return true;
            }
        }
        return false;
    }
}
