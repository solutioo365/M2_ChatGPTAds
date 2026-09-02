<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Solutioo\ChatGptProductSearch\Model\AttributeCodes;

class AddChatGptProductAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attributes = [
            AttributeCodes::SEARCH => [
                'type' => 'int',
                'label' => 'In Produktsuche anzeigen',
                'input' => 'boolean',
                'source' => Boolean::class,
                'required' => false,
                'default' => '1',
                'sort_order' => 10,
                'note' => 'Produkt im ChatGPT-Katalog ausspielen.',
            ],
            AttributeCodes::CHECKOUT => [
                'type' => 'int',
                'label' => 'Checkout über ChatGPT',
                'input' => 'boolean',
                'source' => Boolean::class,
                'required' => false,
                'default' => '0',
                'sort_order' => 20,
                'note' => 'Standard aus. Der Kauf bleibt im Shop.',
            ],
            AttributeCodes::EXCLUDE => [
                'type' => 'int',
                'label' => 'Vom Feed ausschließen',
                'input' => 'boolean',
                'source' => Boolean::class,
                'required' => false,
                'default' => '0',
                'sort_order' => 30,
            ],
            AttributeCodes::GTIN => [
                'type' => 'varchar',
                'label' => 'GTIN / EAN',
                'input' => 'text',
                'required' => false,
                'sort_order' => 40,
                'note' => 'Nur nutzen, wenn kein anderes GTIN-Attribut gemappt ist.',
            ],
            AttributeCodes::MPN => [
                'type' => 'varchar',
                'label' => 'MPN',
                'input' => 'text',
                'required' => false,
                'sort_order' => 50,
            ],
        ];

        foreach ($attributes as $code => $attr) {
            if ($eavSetup->getAttributeId(Product::ENTITY, $code)) {
                continue;
            }
            $eavSetup->addAttribute(Product::ENTITY, $code, $attr + [
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => '',
                'group' => AttributeCodes::GROUP,
            ]);
        }

        $this->moduleDataSetup->getConnection()->endSetup();
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
