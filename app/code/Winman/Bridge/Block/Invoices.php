<?php
/**
 * @author Lynn Eagleton <support@winman.com>
 */

namespace Winman\Bridge\Block;

use \Magento\Framework\View\Element\Template;
use \Winman\Bridge\Helper\Data;
use \Magento\Customer\Model\SessionFactory;
use \Magento\Directory\Model\CurrencyFactory;
use \Magento\Directory\Model\ResourceModel\Country\Collection as CountryCollection;

/**
 * Class Invoices
 *
 * @package Winman\Bridge\Block
 */
class Invoices extends Template
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
     * @var \Magento\Directory\Model\ResourceModel\Country\Collection
     */
    protected $_countryCollection;

    /**
     * Invoices constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Winman\Bridge\Helper\Data $helper
     * @param \Magento\Customer\Model\SessionFactory $customerSessionFactory
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Directory\Model\ResourceModel\Country\Collection $countryCollection
     */
    public function __construct(
        Template\Context $context,
        Data $helper,
        SessionFactory $customerSessionFactory,
        CurrencyFactory $currencyFactory,
        CountryCollection $countryCollection)
    {
        parent::__construct($context);

        $this->_helper = $helper;
        $this->_customerSessionFactory = $customerSessionFactory;
        $this->_currencyFactory = $currencyFactory;
        $this->_countryCollection = $countryCollection;

        $this->_websiteCode = $this->_storeManager->getStore()->getWebsite()->getCode();
    }

    /**
     * Fetch the statement lines from the WinMan REST API. If $id is defined, fetch only
     * the specified invoice.
     * Results can be ordered by salesinvoiceid, date, status or value.
     *
     * @param string|null $id
     * @param string $orderBy
     * @return string|mixed
     */
    public function getStatements($id = null, $orderBy = 'date')
    {
        if (!is_null($id)) {
            return $this->getInvoice($id);
        }

        try {
            $apiUrl = $this->_helper->getApiBaseUrl($this->_websiteCode)
                . '/statements?website='
                . urlencode($this->_helper->getWinmanWebsite($this->_websiteCode))
                . '&customerguid=' . $this->_customerSessionFactory->create()->getCustomer()->getGuid()
                . '&orderby=' . $orderBy
                . '&page=' . $this->getPage() . '&size=' . $this->getPageSize();
        } catch (\Exception $e) {
            return '';
        }

        $response = $this->_helper->executeCurl($this->_websiteCode, $apiUrl);

        if ($response && isset($response->CustomerStatements) && count($response->CustomerStatements) > 0) {
            return $response->CustomerStatements;
        }

        return '';
    }

    /**
     * Fetch the specified invoice from the WinMan REST API.
     *
     * @param string $id
     * @return string|mixed
     */
    public function getInvoice($id)
    {
        try {
            $apiUrl = $this->_helper->getApiBaseUrl($this->_websiteCode)
                . '/invoices?website='
                . urlencode($this->_helper->getWinmanWebsite($this->_websiteCode))
                . '&customerguid=' . $this->_customerSessionFactory->create()->getCustomer()->getGuid()
                . '&salesinvoiceid=' . $id;
        } catch (\Exception $e) {
            return '';
        }

        $response = $this->_helper->executeCurl($this->_websiteCode, $apiUrl);

        if ($response && isset($response->CustomerInvoices) && count($response->CustomerInvoices) > 0) {
            return $response->CustomerInvoices;
        }

        return '';
    }

    /**
     * Fetch the relevant currency symbol based on the currency code.
     *
     * @param string $currencyCode
     * @return string
     */
    public function getCurrencySymbol($currencyCode)
    {
        return $this->_currencyFactory->create()
            ->load($currencyCode)->getCurrencySymbol();
    }

    /**
     * Fetch the full country name based on the 3-character country code.
     *
     * @param string $countryCode
     * @return string
     */
    public function getCountryName($countryCode)
    {
        try {
            $countryName = $this->_countryCollection
                ->addFieldToFilter('iso3_code', $countryCode)
                ->getFirstItem()
                ->getName();
        } catch (\Exception $e) {
            $countryName = __('Unknown');
        }

        return $countryName;
    }

    /**
     * Determine the current page number. The default is 1.
     *
     * @return integer
     */
    public function getPage()
    {
        $page = $this->getRequest()->getParam('page');

        if ($page > $this->getTotalPages()) {
            $page = $this->getTotalPages();
        }

        return ($page) ? $page : 1;
    }

    /**
     * Determine page size currently in use. The default is 10 and the
     * available options are 10, 20 or 50.
     *
     * @return integer
     */
    public function getPageSize()
    {
        $size = $this->getRequest()->getParam('size');
        $size = ($size) ? $size : 10;

        if ($size <= 10) {
            $size = 10;
        } elseif ($size > 10 && $size <= 20) {
            $size = 20;
        } else {
            $size = 50;
        }

        return $size;
    }

    /**
     * Determine the first record number being shown on the current page
     * for the 'Showing items...' message at the bottom of the page.
     *
     * @return integer
     */
    public function getPageStart()
    {
        $start = (($this->getPage() - 1) * $this->getPageSize()) + 1;
        return $start;
    }

    /**
     * Determine the last record number being shown on the current page
     * for the 'Showing items...' message at the bottom of the page.
     *
     * @return integer
     */
    public function getPageEnd()
    {
        if ($this->getTotalCount() < ($this->getPageSize() * $this->getPage())) {
            return $this->getTotalCount();
        }

        return ($this->getPageSize() * $this->getPage());
    }

    /**
     * Determine the total number of records by querying the WinMan REST API
     * for the x-total-count header.
     *
     * @return integer
     */
    public function getTotalCount()
    {
        try {
            $apiUrl = $this->_helper->getApiBaseUrl($this->_websiteCode)
                . '/statements?website='
                . urlencode($this->_helper->getWinmanWebsite($this->_websiteCode))
                . '&customerguid=' . $this->_customerSessionFactory->create()->getCustomer()->getGuid();
        } catch (\Exception $e) {
            return 0;
        }

        $response = $this->_helper->executeCurl($this->_websiteCode, $apiUrl, null, false, true);

        if (is_array($response)) {
            return $response['x-total-count'];
        }

        return 0;
    }

    /**
     * Determine the total number of pages based on the total
     * number of records and page size.
     *
     * @return integer
     */
    public function getTotalPages()
    {
        $totalCount = $this->getTotalCount();
        $pageSize = $this->getPageSize();

        $numberOfPages = (($totalCount - ($totalCount % $pageSize)) / $pageSize) + ((($totalCount % $pageSize)) > 1 ? 1 : 0);

        return $numberOfPages;
    }

    /**
     * Generate an array of page numbers to display. We display the first page number, the last
     * page number and 5 page numbers in between (if applicable, as determined by this function).
     * Other page numbers are accessible via next and previous (ellipsis) page markers.
     *
     * @return array
     */
    public function getVisiblePages()
    {
        if (($this->getPage() % 5) === 0) {
            $start = $this->getPage() - 4;
            $end = $this->getPage();
        } else {
            $start = $this->getPage() - (($this->getPage() % 5) - 1);
            $end = $this->getPage() + (5 - ($this->getPage() % 5));
        }

        if ($end > $this->getTotalPages()) {
            $end = $this->getTotalPages();
        }

        return range($start, $end);
    }

    /**
     * Determine whether to show the previous page (ellipsis) marker.
     *
     * @return boolean
     */
    public function showJumpBack()
    {
        if ($this->getVisiblePages()[0] == 1) {
            return false;
        }

        return true;
    }

    /**
     * Generate the URL to use for the previous page (ellipsis) marker.
     *
     * @return string
     */
    public function getJumpBackUrl()
    {
        return $this->getUrl('customerportal/portal/invoices')
            . '?page=' . ($this->getVisiblePages()[0] - 1)
            . '&size=' . $this->getPageSize();
    }

    /**
     * Determine whether to show the next page (ellipsis) marker.
     *
     * @return boolean
     */
    public function showJumpAhead()
    {
        $pages = $this->getVisiblePages();

        if (end($pages) == $this->getTotalPages()) {
            return false;
        }

        return true;
    }

    /**
     * Generate the URL to use for the next page (ellipsis) marker.
     *
     * @return string
     */
    public function getJumpAheadUrl()
    {
        $visiblePages = $this->getVisiblePages();

        return $this->getUrl('customerportal/portal/invoices')
            . '?page=' . (end($visiblePages) + 1)
            . '&size=' . $this->getPageSize();
    }

    /**
     * Generate the URL to use for the next page (arrow) marker.
     *
     * @return string
     */
    public function getNextUrl()
    {
        return $this->getUrl('customerportal/portal/invoices')
            . '?page=' . ($this->getPage() + 1)
            . '&size=' . $this->getPageSize();
    }

    /**
     * Generate the URL to use for the previous page (arrow) marker.
     *
     * @return string
     */
    public function getPreviousUrl()
    {
        return $this->getUrl('customerportal/portal/invoices')
            . '?page=' . ($this->getPage() - 1)
            . '&size=' . $this->getPageSize();
    }

    /**
     * Generate the URL to use for the specified page number marker.
     *
     * @param integer $page
     * @return string
     */
    public function getPageUrl($page)
    {
        return $this->getUrl('customerportal/portal/invoices')
            . '?page=' . $page
            . '&size=' . $this->getPageSize();
    }

    /**
     * Generate the URL to use for the specified page size drop-down option.
     *
     * @param integer $size
     * @return string
     */
    public function getSizeUrl($size)
    {
        return $this->getUrl('customerportal/portal/invoices')
            . '?page=' . $this->getPage()
            . '&size=' . $size;
    }

    /**
     * Determine whether the specified page size is the size currently in use
     * so that the correct drop-down option is shown as selected.
     *
     * @param integer $size
     * @return string
     */
    public function getSelectedStatus($size)
    {
        return ($size == $this->getPageSize()) ? ' selected="selected"' : '';
    }

    /**
     * Place the shipping invoice item at the end of the array so it appears last
     * in the list of invoice items.
     *
     * @param array $array
     * @return array
     */
    public function sortItems($array)
    {
        usort($array, function ($a) {
            if (isset($a->FreightMethodId)) {
                return 1;
            }

            return 0;
        });

        return $array;
    }
}
