<?php

namespace Winman\Bridge\Helper;

use \Magento\Framework\App\Helper;
use \Winman\Bridge\Logger\Logger as WinmanLogger;
use \Magento\Store\Model\ScopeInterface;

/**
 * Class Data
 *
 * @package Winman\Bridge\Helper
 */
class Data extends Helper\AbstractHelper
{
    /**
     * @var \Winman\Bridge\Logger\Logger $_winmanLogger
     */
    protected $_winmanLogger;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Winman\Bridge\Logger\Logger $logger
     */
    public function __construct(
        Helper\Context $context,
        WinmanLogger $logger)
    {
        parent::__construct($context);
        $this->_winmanLogger = $logger;
    }

    /**
     * Retrieve config values for the WinMan Bridge.
     *
     * @param string $configPath The path through the tree of config values, e.g. 'winman_bridge/general/enable'.
     * @param string $websiteCode The relevant website code.
     * @return mixed
     */
    public function getConfig($configPath, $websiteCode)
    {
        return $this->scopeConfig->getValue(
            $configPath,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/general/enable (Enable Bridge).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getEnabled($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/general/enable',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/general/api_baseurl (API Base URL).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getApiBaseUrl($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/general/api_baseurl',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/general/winman_website (Website URL).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getWinmanWebsite($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/general/winman_website',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/general/access_token (API Access Token).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getAccessToken($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/general/access_token',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/general/enable_logging (Enable Bridge Logging).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getEnableLogging($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/general/enable_logging',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/products/enable_products (Fetch Product from WinMan).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getEnableProducts($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/products/enable_products',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/products/enable_stock (Fetch Product Stock Levels from WinMan).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getEnableStock($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/products/enable_stock',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/products/enable_product_images (Fetch Product Images from WinMan).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getEnableProductImages($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/products/enable_product_images',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/products/enable_product_categories (Fetch Product Categories from WinMan).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getEnableProductCategories($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/products/enable_product_categories',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/products/full_product_update (Perform Full Product Update).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getFullProductUpdate($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/products/full_product_update',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/products/full_product_category_update (Perform Full Product Category Update).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getFullProductCategoryUpdate($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/products/full_product_category_update',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/customers/enable_customers (Fetch Customers from WinMan).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getEnableCustomers($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/customers/enable_customers',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/customers/email_customers (Send New Customers a Welcome Email).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getEmailCustomers($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/customers/email_customers',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/customers/full_customer_update (Perform Full Customer Update).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getFullCustomerUpdate($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/customers/full_customer_update',
            $websiteCode
        );
    }

    /**
     * Retrieve the config value for winman_bridge/sales_orders/enable_salesorders (Push Sales Orders back to WinMan).
     *
     * @param $websiteCode
     * @return mixed
     */
    public function getEnableSalesOrders($websiteCode)
    {
        return $this->getConfig(
            'winman_bridge/sales_orders/enable_salesorders',
            $websiteCode
        );
    }

    /**
     * Get the necessary cURL headers to perform a cURL request to the WinMan REST API.
     *
     * @param $websiteCode
     * @return array
     */
    private function getCurlHeaders($websiteCode)
    {
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authorization: Bearer ' . $this->getConfig('winman_bridge/general/access_token', $websiteCode)
        ];

        return $headers;
    }

    /**
     * Perform a cURL request to the specified endpoint of the WinMan REST API.
     * If $data is specified, we are sending data back to WinMan. Otherwise, we are retrieving data from WinMan.
     *
     * @param string $websiteCode
     * @param string $apiUrl
     * @param array|null $data
     * @param bool $isPut
     * @return mixed|bool
     */
    public function executeCurl($websiteCode, $apiUrl, $data = null, $isPut = false)
    {
        $curl = curl_init($apiUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->getCurlHeaders($websiteCode));

        /**
         * When sending data back to WinMan, some endpoints use the POST HTTP verb, others use PUT.
         * We must explicitly declare which we wish to use.
         */
        if (!is_null($data)) {
            if ($isPut) {
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
            } else {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        /**
         * If there was no response from the WinMan REST API, log the error
         * regardless of whether logging is enabled for the WinMan Bridge.
         */
        if (!$response) {
            $this->_winmanLogger->critical('Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl));
            return false;
        }

        $decoded = json_decode($response);
        curl_close($curl);

        return $decoded;
    }
}
