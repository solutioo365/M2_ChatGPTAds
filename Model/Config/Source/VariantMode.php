<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class VariantMode implements OptionSourceInterface
{
    public const ALL = 'all';
    public const PARENTS = 'parents';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::ALL, 'label' => __('Alle Varianten (empfohlen)')],
            ['value' => self::PARENTS, 'label' => __('Nur Hauptprodukte')],
        ];
    }
}
