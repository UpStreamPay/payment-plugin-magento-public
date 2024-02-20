# Subscription product attributes

## Goal

To configure the subscription you will have to create 2 product attributes :

    - one which will determine if the product is sibscription eligible
    - one that will be used to set the subscription's duration

## Magento installer example

```php
<?php
/**
 * UpStream Pay
 *
 * Copyright (c) 2023 UpStream Pay.
 * This file is open source and available under the BSD 3 license.
 * See the LICENSE file for more info.
 *
 * Author: Claranet France <info@fr.clara.net>
 */
declare(strict_types=1);

namespace UpStreamPay\Core\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

class SubscriptionProductAttributesPatch implements DataPatchInterface
{

    public function __construct
    (
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    )
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(): void

    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute('catalog_product', 'is_subscription_eligible', [
            'type' => 'int',
            'label' => 'Is subscription eligible',
            'input' => 'select',
            'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
            'default' => 0,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'used_in_product_listing' => true,
            'user_defined' => true,
            'required' => false,
            'group' => 'General'
        ]);

        $eavSetup->addAttribute('catalog_product', 'subscription_duration', [
            'type' => 'int',
            'label' => 'Subscription duration',
            'input' => 'text',
            'default' => 30,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'used_in_product_listing' => true,
            'user_defined' => true,
            'required' => false,
            'group' => 'General'
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
```