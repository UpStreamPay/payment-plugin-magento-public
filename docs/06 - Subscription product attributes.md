# Subscription product attributes

## Goal

To configure the subscription you will have to create 2 product attributes:

    - one which will determine if the product is a product with a subscription
    - one that will be used to determine the subscription's duration (in days)

## Magento installer example

```php
<?php

declare(strict_types=1);

namespace YOUR\NAMESPACE;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

/**
* Class SubscriptionProductAttributesPatch
 *
 * @package YOUR\NAMESPACE
 */
class SubscriptionProductAttributesPatch implements DataPatchInterface
{

    /**
    * @param ModuleDataSetupInterface $moduleDataSetup
    * @param EavSetupFactory $eavSetupFactory
    */
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

        $eavSetup->addAttribute('catalog_product', 'YOUR_ATTRIBUTE_CODE', [
            'type' => 'int', //DO NOT CHANGE
            'label' => 'YOUR LABEL',
            'input' => 'select', //DO NOT CHANGE
            'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
            'default' => 0,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'used_in_product_listing' => true, //DO NOT CHANGE
            'user_defined' => true,
            'required' => false,
            'group' => 'YOUR GROUP'
        ]);

        $eavSetup->addAttribute('catalog_product', 'YOUR_ATTRIBUTE_CODE', [
            'type' => 'int', //DO NOT CHANGE
            'label' => 'YOUR LABEL',
            'input' => 'text', //DO NOT CHANGE
            'default' => 30,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'used_in_product_listing' => true, //DO NOT CHANGE
            'user_defined' => true,
            'required' => false,
            'group' => 'YOUR GROUP'
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

## Using the attributes

After you have created the attributes you must populate the values on the product you would like to flag as subscription.

You must configure both attributes for it to work & the duration of the subscription cannot be 0.

Once you have defined values for some of your products, it would be best to reindex the product attributes / catalog product flat or any other custom index you might have that that would require it.

All you have to do then is to update the admin config and let Magento what are the attribute code of the new attributes, save the config & clean the cache!
