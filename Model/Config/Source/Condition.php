<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Condition implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'new', 'label' => __('Neu')],
            ['value' => 'refurbished', 'label' => __('Refurbished')],
            ['value' => 'used', 'label' => __('Gebraucht')],
            ['value' => 'secondhand', 'label' => __('Secondhand')],
        ];
    }
}
