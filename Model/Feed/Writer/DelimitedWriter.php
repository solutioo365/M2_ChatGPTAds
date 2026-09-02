<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Feed\Writer;

use Solutioo\ChatGptProductSearch\Model\Config\Source\FeedFormat;

class DelimitedWriter
{
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function write(array $rows, string $format): string
    {
        $delimiter = in_array($format, [FeedFormat::OPENAI_TSV, FeedFormat::GOOGLE_TSV], true) ? "\t" : ',';
        $columns = $this->columns($format);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Feed-Puffer konnte nicht geöffnet werden.');
        }
        fputcsv($handle, $columns, $delimiter);
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = (string) ($row[$column] ?? '');
            }
            fputcsv($handle, $line, $delimiter);
        }
        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);
        return $content;
    }

    /**
     * @return string[]
     */
    private function columns(string $format): array
    {
        if (in_array($format, [FeedFormat::GOOGLE_CSV, FeedFormat::GOOGLE_TSV], true)) {
            return [
                'id',
                'title',
                'description',
                'link',
                'image_link',
                'additional_image_link',
                'availability',
                'price',
                'sale_price',
                'sale_price_effective_date',
                'brand',
                'gtin',
                'mpn',
                'identifier_exists',
                'condition',
                'product_type',
                'google_product_category',
                'item_group_id',
                'item_group_title',
                'color',
                'size',
                'material',
                'age_group',
                'gender',
                'seller_name',
            ];
        }

        return [
            'is_eligible_search',
            'is_eligible_checkout',
            'is_ads_eligible',
            'item_id',
            'gtin',
            'mpn',
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
        ];
    }
}
