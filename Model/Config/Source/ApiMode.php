<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ApiMode implements OptionSourceInterface
{
    public const COMMERCE = 'commerce';
    public const ADS = 'ads';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::ADS, 'label' => __('OpenAI Ads / Product Feed (api.ads.openai.com)')],
            ['value' => self::COMMERCE, 'label' => __('Commerce API (api.openai.com)')],
        ];
    }
}
