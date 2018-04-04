<?php
/**
 * @author Lynn Eagleton <support@winman.com>
 */

namespace Winman\Bridge\Block;

use \Magento\Framework\View\Element\Template;
use \Magento\Directory\Model\AllowedCountries;
use \Magento\Directory\Model\CountryFactory;
use \Winman\Bridge\Helper\Data;

/**
 * Class RequestAccount
 *
 * @package Winman\Bridge\Block
 */
class RequestAccount extends Template
{
    /**
     * @var \Magento\Directory\Model\AllowedCountries
     */
    protected $_allowedCountries;
    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    protected $_countryFactory;
    /**
     * @var \Winman\Bridge\Helper\Data
     */
    protected $_helper;

    /**
     * RequestAccount constructor.
     *
     * @param \Magento\Directory\Model\AllowedCountries $allowedCountries
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Winman\Bridge\Helper\Data $helper
     * @param \Magento\Framework\View\Element\Template\Context $context
     */
    public function __construct(
        AllowedCountries $allowedCountries,
        CountryFactory $countryFactory,
        Data $helper,
        Template\Context $context)
    {
        parent::__construct($context);

        $this->_allowedCountries = $allowedCountries;
        $this->_countryFactory = $countryFactory;
        $this->_helper = $helper;
    }

    /**
     * Get the current store ID.
     *
     * @return integer
     */
    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }

    /**
     * Get the action for the Request Account form.
     *
     * @return string
     */
    public function getFormAction()
    {
        return '/requestaccount';
    }

    /**
     * Determine whether the WinMan Bridge is enabled.
     *
     * @return boolean
     */
    public function isBridgeEnabled()
    {
        return $this->_helper->getEnabled($this->_storeManager->getStore()->getWebsite()->getCode());
    }

    /**
     * Get the HTML for the drop-down list of countries.
     *
     * @return string
     */
    public function getCountries()
    {
        $html = '<option value="">Please Select...</option>';

        $countries = $this->_allowedCountries
            ->getAllowedCountries('store', $this->getStoreId());

        foreach ($countries as $code) {
            $country = $this->_countryFactory
                ->create()
                ->loadByCode($code);
            $html .= '<option value="' . $country->getData('iso3_code') . '">' . $country->getName() . '</option>';
        }

        return $html;
    }
}

