<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Config\Source;

use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Framework\Data\OptionSourceInterface;

class Visibility implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => ProductVisibility::VISIBILITY_IN_CATALOG, 'label' => __('Nur Katalog')],
            ['value' => ProductVisibility::VISIBILITY_IN_SEARCH, 'label' => __('Nur Suche')],
            ['value' => ProductVisibility::VISIBILITY_BOTH, 'label' => __('Katalog und Suche')],
            ['value' => ProductVisibility::VISIBILITY_NOT_VISIBLE, 'label' => __('Nicht sichtbar (z. B. Varianten)')],
        ];
    }
}
