<?php

namespace Winman\Bridge\Block;

use \Magento\Framework\View\Element\Template;
use \Winman\Bridge\Helper\Data;
use \Magento\Customer\Model\SessionFactory;
use \Magento\Directory\Model\CurrencyFactory;

/**
 * Class Overview
 *
 * @package Winman\Bridge\Block
 */
class Overview extends Template
{
    /**
     * @var \Winman\Bridge\Helper\Data
     */
    protected $_helper;

    /**
     * @var mixed
     */
    protected $_websiteCode;

    /**
     * @var \Magento\Customer\Model\SessionFactory
     */
    protected $_customerSessionFactory;

    /**
     * @var \Magento\Directory\Model\CurrencyFactory
     */
    protected $_currencyFactory;

    /**
     * Overview constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Winman\Bridge\Helper\Data $helper
     * @param \Magento\Customer\Model\SessionFactory $customerSessionFactory
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     */
    public function __construct(
        Template\Context $context,
        Data $helper,
        SessionFactory $customerSessionFactory,
        CurrencyFactory $currencyFactory)
    {
        parent::__construct($context);

        $this->_helper = $helper;
        $this->_customerSessionFactory = $customerSessionFactory;
        $this->_currencyFactory = $currencyFactory;

        $this->_websiteCode = $this->_storeManager->getStore()->getWebsite()->getCode();
    }

    /**
     * @return mixed
     */
    public function getWinmanData()
    {
        $data = (object)[
            'Customer' => '',
            'AccountOverview' => ''
        ];

        $data->Customer = $this->getCustomer();
        $data->AccountOverview = $this->getAccountOverview();

        return $data;
    }

    /**
     * @return string|mixed
     */
    private function getAccountOverview()
    {
        $apiUrl = $this->_helper->getApiBaseUrl($this->_websiteCode)
            . '/accountoverviews?website='
            . urlencode($this->_helper->getWinmanWebsite($this->_websiteCode))
            . '&customerguid=' . $this->_customerSessionFactory->create()->getCustomer()->getGuid();

        $response = $this->_helper->executeCurl($this->_websiteCode, $apiUrl);

        if ($response && isset($response->CustomerAccountOverviews[0])) {
            return $response->CustomerAccountOverviews[0];
        }

        return '';
    }

    /**
     * @return string|mixed
     */
    private function getCustomer()
    {
        $apiUrl = $this->_helper->getApiBaseUrl($this->_websiteCode)
            . '/customers?website='
            . urlencode($this->_helper->getWinmanWebsite($this->_websiteCode))
            . '&customerguid=' . $this->_customerSessionFactory->create()->getCustomer()->getGuid();

        $response = $this->_helper->executeCurl($this->_websiteCode, $apiUrl);

        if ($response && isset($response->Customers[0])) {
            return $response->Customers[0];
        }

        return '';
    }

    /**
     * @return string|mixed
     */
    public function getRecentOrders()
    {
        $apiUrl = $this->_helper->getApiBaseUrl($this->_websiteCode)
            . '/salesorders?website='
            . urlencode($this->_helper->getWinmanWebsite($this->_websiteCode))
            . '&customerguid=' . $this->_customerSessionFactory->create()->getCustomer()->getGuid()
            . '&orderby=date&size=5';

        $response = $this->_helper->executeCurl($this->_websiteCode, $apiUrl);

        if ($response && isset($response->CustomerOrders)) {
            return $response->CustomerOrders;
        }

        return '';
    }

    /**
     * @return string|mixed
     */
    public function getRecentInvoices()
    {
        $invoices = [];
        $page = 1;

        $apiUrlBase = $this->_helper->getApiBaseUrl($this->_websiteCode)
            . '/statements?website='
            . urlencode($this->_helper->getWinmanWebsite($this->_websiteCode))
            . '&customerguid=' . $this->_customerSessionFactory->create()->getCustomer()->getGuid()
            . '&orderby=date&size=5';

        while (count($invoices) < 5) {
            $apiUrl = $apiUrlBase . '&page=' . $page;
            $response = $this->_helper->executeCurl($this->_websiteCode, $apiUrl);

            if ($response && isset($response->CustomerStatements) && count($response->CustomerStatements) > 0) {
                foreach ($response->CustomerStatements as $statementLine) {
                    if ($statementLine->StatementLineType === 'Invoice') {
                        $invoices[] = $statementLine;
                    }
                }
            } else {
                break;
            }

            $page++;
        }

        if (count($invoices) > 0) {
            return $invoices;
        }

        return '';
    }

    /**
     * @param string $currencyCode
     * @return string
     */
    public function getCurrencySymbol($currencyCode)
    {
        return $this->_currencyFactory->create()
            ->load($currencyCode)->getCurrencySymbol();
    }
}
