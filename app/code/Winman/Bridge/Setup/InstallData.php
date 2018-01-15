<?php

namespace Winman\Bridge\Setup;

use \Magento\Eav\Setup\EavSetupFactory;
use \Magento\Framework\Setup\InstallDataInterface;
use \Magento\Framework\Setup\ModuleContextInterface;
use \Magento\Framework\Setup\ModuleDataSetupInterface;
use \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use \Magento\Catalog\Model\Category;
use \Magento\Catalog\Model\Product;
use \Magento\Customer\Model\Customer;

/**
 * Class InstallData
 * @package Winman\Bridge\Setup
 */
class InstallData implements InstallDataInterface
{
    private $_eavSetupFactory;

    /**
     * InstallData constructor.
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->_eavSetupFactory = $eavSetupFactory;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->_eavSetupFactory->create(['setup' => $setup]);

        $eavSetup->addAttribute(
            Category::ENTITY,
            'guid',
            [
                'type' => 'varchar',
                'label' => 'Category GUID',
                'input' => 'text',
                'required' => false,
                'sort_order' => 4,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'group' => 'General Information',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'unit_of_measure',
            [
                'type' => 'varchar',
                'label' => 'Unit of Measure',
                'input' => 'text',
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => 'simple,configurable',
                'group' => 'WinMan Attributes',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'barcode',
            [
                'type' => 'varchar',
                'label' => 'Barcode',
                'input' => 'text',
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => false,
                'searchable' => true,
                'filterable' => true,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => 'simple,configurable',
                'group' => 'WinMan Attributes',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'pack_size',
            [
                'type' => 'int',
                'label' => 'Pack Size',
                'input' => 'text',
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => 'simple,configurable',
                'group' => 'WinMan Attributes',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'length',
            [
                'type' => 'decimal',
                'label' => 'Length',
                'input' => 'text',
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => 'simple,configurable',
                'group' => 'WinMan Attributes',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'width',
            [
                'type' => 'decimal',
                'label' => 'Width',
                'input' => 'text',
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => 'simple,configurable',
                'group' => 'WinMan Attributes',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'height',
            [
                'type' => 'decimal',
                'label' => 'Height',
                'input' => 'text',
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => 'simple,configurable',
                'group' => 'WinMan Attributes',
            ]
        );

        $eavSetup->addAttribute(
            Customer::ENTITY,
            'guid',
            [
                'type' => 'varchar',
                'label' => 'GUID',
                'input' => 'text',
                'required' => false,
                'system' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'adminhtml_only' => true,
                'user_defined' => false,
            ]
        );

        $eavSetup->addAttribute(
            Customer::ENTITY,
            'allow_communication',
            [
                'type' => 'int',
                'label' => 'Communication Allowed',
                'input' => 'select',
                'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                'required' => false,
                'default' => '0',
                'system' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            ]
        );
    }
}
