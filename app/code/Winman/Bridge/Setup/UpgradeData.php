<?php
/**
 * @author Lynn Eagleton <support@winman.com>
 */

namespace Winman\Bridge\Setup;

use \Magento\Eav\Setup\EavSetupFactory;
use \Magento\Framework\Setup\UpgradeDataInterface;
use \Magento\Framework\Setup\ModuleContextInterface;
use \Magento\Framework\Setup\ModuleDataSetupInterface;
use \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use \Magento\Eav\Api\AttributeRepositoryInterface;
use \Magento\Customer\Model\Customer;
use \Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class UpgradeData
 *
 * @package Winman\Bridge\Setup
 */
class UpgradeData implements UpgradeDataInterface
{
    private $_eavSetupFactory;
    private $_attributeRepository;

    /**
     * UpgradeData constructor.
     *
     * @param EavSetupFactory $eavSetupFactory
     * @param AttributeRepositoryInterface $attributeRepository
     */
    public function __construct(EavSetupFactory $eavSetupFactory, AttributeRepositoryInterface $attributeRepository)
    {
        $this->_eavSetupFactory = $eavSetupFactory;
        $this->_attributeRepository = $attributeRepository;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /**
         * Versions prior to 1.1.0 may not have created the
         * allow_communication customer attribute correctly due to a bug.
         * Check if it exists and create if it doesn't.
         */
        if (version_compare($context->getVersion(), '1.1.0', '<=')) {
            $eavSetup = $this->_eavSetupFactory->create(['setup' => $setup]);

            try {
                $this->_attributeRepository
                    ->get(Customer::ENTITY, 'allow_communication');
            } catch (NoSuchEntityException $e) {
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
    }
}
