<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FeedFormat implements OptionSourceInterface
{
    public const JSONL = 'jsonl';
    public const OPENAI_CSV = 'openai_csv';
    public const OPENAI_TSV = 'openai_tsv';
    public const GOOGLE_CSV = 'google_csv';
    public const GOOGLE_TSV = 'google_tsv';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::JSONL, 'label' => __('OpenAI JSONL (empfohlen, Varianten verschachtelt)')],
            ['value' => self::OPENAI_CSV, 'label' => __('OpenAI Flat CSV')],
            ['value' => self::OPENAI_TSV, 'label' => __('OpenAI Flat TSV')],
            ['value' => self::GOOGLE_CSV, 'label' => __('Google Merchant kompatibel (CSV)')],
            ['value' => self::GOOGLE_TSV, 'label' => __('Google Merchant kompatibel (TSV)')],
        ];
    }
}
