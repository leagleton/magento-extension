<?php

namespace Winman\Bridge\Block;

use \Magento\Directory\Block\Data;
use \Magento\Framework\View\Element\Template;
use \Magento\Directory\Model\AllowedCountries;
use \Magento\Store\Model\ScopeInterface;
use \Magento\Directory\Model\CountryFactory;
use \Winman\Bridge\Helper\Data as Helper;

/**
 * Class RequestAccount
 * @package Winman\Bridge\Block
 */
class RequestAccount extends Template
{

    private $_ENABLED;

    protected $_directoryBlock;
    protected $_allowedCountries;
    protected $_countryFactory;
    protected $_helper;

    /**
     * RequestAccount constructor.
     * @param Data $directoryBlock
     * @param AllowedCountries $allowedCountries
     * @param CountryFactory $countryFactory
     * @param Helper $helper
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        Data $directoryBlock,
        AllowedCountries $allowedCountries,
        CountryFactory $countryFactory,
        Helper $helper,
        Template\Context $context,
        array $data = [])
    {
        parent::__construct($context, $data);
        $this->_directoryBlock = $directoryBlock;
        $this->_allowedCountries = $allowedCountries;
        $this->_countryFactory = $countryFactory;
        $this->_helper = $helper;

        $websiteCode = $this->_storeManager->getStore()->getWebsite()->getCode();
        $this->_ENABLED = $this->_helper->getconfig('winman_bridge/general/enable', $websiteCode);
    }

    /**
     * @return int
     */
    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }

    /**
     * @return string
     */
    public function getFormAction()
    {
        return '/requestaccount';
    }

    /**
     * @return bool
     */
    public function isBridgeEnabled()
    {
        if ($this->_ENABLED) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getCountries()
    {
        $html = '<option value="">Please Select...</option>';

        $countries = $this->_allowedCountries
            ->getAllowedCountries(ScopeInterface::SCOPE_STORE, $this->getStoreId());

        foreach ($countries as $code) {
            $country = $this->_countryFactory
                ->create()
                ->loadByCode($code);
            $html .= '<option value="' . $country->getData('iso3_code') . '">' . $country->getName() . '</option>';
        }

        return $html;
    }
}

