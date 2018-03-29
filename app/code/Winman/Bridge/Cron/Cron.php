<?php
/**
 * @author Lynn Eagleton <support@winman.com>
 */

namespace Winman\Bridge\Cron;

use \Winman\Bridge\Logger\Logger;
use \Winman\Bridge\Helper\Data;
use \Magento\Catalog\Model\ProductFactory;
use \Magento\Catalog\Model\ProductRepository;
use \Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory as OptionFactory;
use \Magento\Catalog\Api\Data\ProductCustomOptionValuesInterfaceFactory as OptionValuesFactory;
use \Magento\CatalogInventory\Api\StockRegistryInterface;
use \Magento\Framework\Filesystem;
use \Magento\Catalog\Model\CategoryFactory;
use \Magento\Catalog\Model\CategoryRepository;
use \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use \Magento\Catalog\Api\CategoryLinkManagementInterface;
use \Magento\Customer\Api\Data\CustomerInterfaceFactory as CustomerFactory;
use \Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use \Magento\Customer\Model\ResourceModel\Group\CollectionFactory as CustomerGroupCollectionFactory;
use \Magento\Customer\Api\Data\GroupInterfaceFactory as CustomerGroupFactory;
use \Magento\Customer\Api\GroupRepositoryInterface as CustomerGroupRepository;
use \Magento\Framework\Mail\Template\TransportBuilder;
use \Magento\Customer\Api\GroupManagementInterface;
use \Magento\Customer\Api\Data\AddressInterfaceFactory as AddressFactory;
use \Magento\Customer\Api\AddressRepositoryInterface as AddressRepository;
use \Magento\Directory\Model\ResourceModel\Country\Collection as CountryCollection;
use \Magento\Store\Model\StoreManager;
use \Magento\Tax\Model\ResourceModel\TaxClass\CollectionFactory as TaxClassCollectionFactory;
use \Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use \Magento\Framework\App\ResourceConnection;
use \Magento\Eav\Model\Config as EavConfig;
use \Magento\Framework\App\Config;
use \Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use \Magento\Framework\Api\SearchCriteriaBuilder;
use \Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use \Magento\Catalog\Model\Product\WebsiteFactory AS ProductWebsiteFactory;

/**
 * Class Cron
 *
 * @package Winman\Bridge\Cron
 */
class Cron
{
    /**
     * @var \Winman\Bridge\Logger\Logger
     */
    protected $_logger;

    /**
     * @var \Winman\Bridge\Helper\Data
     */
    protected $_helper;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $_productFactory;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $_productRepository;

    /**
     * @var \Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory
     */
    protected $_optionFactory;

    /**
     * @var \Magento\Catalog\Api\Data\ProductCustomOptionValuesInterfaceFactory
     */
    protected $_optionValuesFactory;

    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $_stockRegistry;

    /**
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    protected $_categoryFactory;

    /**
     * @var \Magento\Catalog\Model\CategoryRepository
     */
    protected $_categoryRepository;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $_categoryCollectionFactory;

    /**
     * @var \Magento\Catalog\Api\CategoryLinkManagementInterface
     */
    protected $_categoryLinkManagement;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $_customerFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $_customerRepository;

    /**
     * @var \Magento\Customer\Model\ResourceModel\Group\CollectionFactory
     */
    protected $_customerGroupCollectionFactory;

    /**
     * @var \Magento\Customer\Api\GroupRepositoryInterface
     */
    protected $_customerGroupRepository;

    /**
     * @var \Magento\Customer\Api\Data\GroupInterfaceFactory
     */
    protected $_customerGroupFactory;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var \Magento\Customer\Api\GroupManagementInterface
     */
    protected $_groupManagementInterface;

    /**
     * @var \Magento\Customer\Api\Data\AddressInterfaceFactory
     */
    protected $_addressFactory;

    /**
     * @var \Magento\Customer\Api\AddressRepositoryInterface
     */
    protected $_addressRepository;

    /**
     * @var \Magento\Directory\Model\ResourceModel\Country\Collection
     */
    protected $_countryCollection;

    /**
     * @var \Magento\Store\Model\StoreManager
     */
    protected $_storeManager;

    /**
     * @var \Magento\Tax\Model\ResourceModel\TaxClass\CollectionFactory
     */
    protected $_taxClassCollectionFactory;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $_timezoneInterface;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $_fileSystem;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $_resourceConnection;

    /**
     * @var \Magento\Eav\Model\Config
     */
    protected $_eavConfig;

    /**
     * @var \Magento\Framework\App\Config
     */
    protected $_config;

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    protected $_configInterface;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Catalog\Model\Product\WebsiteFactory
     */
    protected $_productWebsiteFactory;

    /**
     * @var string
     */
    private $_mediaPath;

    /**
     * @var integer
     */
    private $_lastExecutedTimestamp;

    /**
     * @var array|\Magento\Store\Api\Data\WebsiteInterface[]
     */
    private $_websites;

    /**
     * @var \Magento\Store\Api\Data\WebsiteInterface
     */
    private $_currentWebsite;

    /**
     * @var boolean
     */
    private $_finishWithErrors = false;

    /**
     * Cron constructor.
     *
     * @param \Winman\Bridge\Logger\Logger $logger
     * @param \Winman\Bridge\Helper\Data $helper
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param \Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory $optionFactory
     * @param \Magento\Catalog\Api\Data\ProductCustomOptionValuesInterfaceFactory $optionValuesFactory
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     * @param \Magento\Catalog\Model\CategoryRepository $categoryRepository
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param \Magento\Catalog\Api\CategoryLinkManagementInterface $categoryLinkManagement
     * @param \Magento\Customer\Api\Data\CustomerInterfaceFactory $customerFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Customer\Model\ResourceModel\Group\CollectionFactory $customerGroupCollectionFactory
     * @param \Magento\Customer\Api\Data\GroupInterfaceFactory $customerGroupFactory
     * @param \Magento\Customer\Api\GroupRepositoryInterface $customerGroupRepository
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Customer\Api\GroupManagementInterface $groupManagementInterface
     * @param \Magento\Customer\Api\Data\AddressInterfaceFactory $addressFactory
     * @param \Magento\Customer\Api\AddressRepositoryInterface $addressRepository
     * @param \Magento\Directory\Model\ResourceModel\Country\Collection $countryCollection
     * @param \Magento\Store\Model\StoreManager $storeManager
     * @param \Magento\Tax\Model\ResourceModel\TaxClass\CollectionFactory $taxClassCollectionFactory
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezoneInterface
     * @param \Magento\Framework\Filesystem $fileSystem
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\Framework\App\Config $config
     * @param \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Catalog\Model\Product\WebsiteFactory $productWebsiteFactory
     */
    public function __construct(
        Logger $logger,
        Data $helper,
        ProductFactory $productFactory,
        ProductRepository $productRepository,
        OptionFactory $optionFactory,
        OptionValuesFactory $optionValuesFactory,
        StockRegistryInterface $stockRegistry,
        CategoryFactory $categoryFactory,
        CategoryRepository $categoryRepository,
        CollectionFactory $categoryCollectionFactory,
        CategoryLinkManagementInterface $categoryLinkManagement,
        CustomerFactory $customerFactory,
        CustomerRepository $customerRepository,
        CustomerGroupCollectionFactory $customerGroupCollectionFactory,
        CustomerGroupFactory $customerGroupFactory,
        CustomerGroupRepository $customerGroupRepository,
        TransportBuilder $transportBuilder,
        GroupManagementInterface $groupManagementInterface,
        AddressFactory $addressFactory,
        AddressRepository $addressRepository,
        CountryCollection $countryCollection,
        StoreManager $storeManager,
        TaxClassCollectionFactory $taxClassCollectionFactory,
        TimezoneInterface $timezoneInterface,
        Filesystem $fileSystem,
        ResourceConnection $resourceConnection,
        EavConfig $eavConfig,
        Config $config,
        ConfigInterface $configInterface,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ScopeConfig $scopeConfig,
        ProductWebsiteFactory $productWebsiteFactory)
    {
        $this->_logger = $logger;
        $this->_helper = $helper;

        $this->_productFactory = $productFactory;
        $this->_productRepository = $productRepository;
        $this->_optionFactory = $optionFactory;
        $this->_optionValuesFactory = $optionValuesFactory;
        $this->_productWebsiteFactory = $productWebsiteFactory;
        $this->_stockRegistry = $stockRegistry;

        $this->_categoryFactory = $categoryFactory;
        $this->_categoryRepository = $categoryRepository;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_categoryLinkManagement = $categoryLinkManagement;

        $this->_customerFactory = $customerFactory;
        $this->_customerRepository = $customerRepository;
        $this->_customerGroupCollectionFactory = $customerGroupCollectionFactory;
        $this->_customerGroupFactory = $customerGroupFactory;
        $this->_customerGroupRepository = $customerGroupRepository;
        $this->_transportBuilder = $transportBuilder;
        $this->_groupManagementInterface = $groupManagementInterface;
        $this->_addressFactory = $addressFactory;
        $this->_addressRepository = $addressRepository;
        $this->_countryCollection = $countryCollection;
        $this->_taxClassCollectionFactory = $taxClassCollectionFactory;

        $this->_storeManager = $storeManager;

        $this->_timezoneInterface = $timezoneInterface;
        $this->_fileSystem = $fileSystem;
        $this->_resourceConnection = $resourceConnection;

        $this->_eavConfig = $eavConfig;
        $this->_config = $config;
        $this->_configInterface = $configInterface;
        $this->_scopeConfig = $scopeConfig;

        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;

        $this->_mediaPath = $this->_fileSystem->getDirectoryRead('media')->getAbsolutePath();
        $this->_lastExecutedTimestamp = $this->getLastExecutedTimestamp();

        $this->_websites = $this->_storeManager->getWebsites(false, true);
    }

    /**
     * Execute the cron job. Throw an exception if any of the updates error so that the
     * timestamp of the last successful run is accurate.
     *
     * @return $this
     * @throws \Exception
     */
    public function execute()
    {
        $websites = $this->_websites;

        foreach ($websites as $code => $website) {
            if ($this->getDefaultStoreId($website)) {
                $this->_currentWebsite = $website;

                if ($this->_helper->getEnabled($code)) {
                    if ($this->_helper->getEnableLogging($code)) {
                        $this->_logger->info(__('WinMan synchronisation started for website') . ': ' . $website->getName());
                    }

                    if ($this->_helper->getEnableProducts($code)) {
                        $this->fetchProducts();
                    }

                    if ($this->_helper->getEnableProductCategories($code)) {
                        $this->fetchCategories();
                    }

                    if ($this->_helper->getEnableCustomers($code)) {
                        $this->fetchCustomers();
                    }

                    $this->disableFullUpdates($website->getId());

                    if ($this->_helper->getEnableLogging($code)) {
                        $this->_logger->info(__('WinMan synchronisation finished for website') . ': ' . $website->getName());
                    }
                }
            }
        }

        if ($this->_finishWithErrors) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning(__('WinMan cron job finished with errors'));
            }
            throw new \Exception(__('WinMan cron job finished with errors'));
        } else {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->info(__('WinMan cron job finished successfully'));
            }
            return $this;
        }
    }

    /**
     * Retrieve the default store ID for the specified website.
     *
     * @param \Magento\Store\Api\Data\WebsiteInterface $website
     * @return integer|boolean
     */
    private function getDefaultStoreId($website)
    {
        if ($group = $website->getDefaultGroupId()) {
            return $this->_storeManager->getGroup($group)
                ->getDefaultStore()
                ->getId();
        }

        return false;
    }

    /**
     * Retrieve the default product attribute set.
     *
     * @return integer|boolean
     */
    private function getDefaultProductAttributeSetId()
    {
        try {
            return $this->_eavConfig
                ->getEntityType('catalog_product')
                ->getDefaultAttributeSetId();
        } catch (\Exception $e) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning($e->getMessage());
            }
            return false;
        }
    }

    /**
     * Retrieve the timestamp of the last successful run of the WinMan cron job.
     *
     * @return integer
     */
    private function getLastExecutedTimestamp()
    {
        $connection = $this->_resourceConnection->getConnection();
        $tableName = $this->_resourceConnection->getTableName('cron_schedule');

        $sql = $connection->select()
            ->from($tableName, array('executed_at'))
            ->where('job_code = ?', 'winman_bridge')
            ->where('status = ?', 'success')
            ->where('finished_at IS NOT NULL')
            ->order('schedule_id DESC')
            ->limit(1);

        $result = $connection->fetchAll($sql);

        if (count($result) > 0) {
            return strtotime($result[0]['executed_at']);
        }

        return 0;
    }

    /**
     * Ensure all of the full update config settings are set to 'No'
     * after the cron job has finished running
     *
     * @param integer $websiteId
     */
    private function disableFullUpdates($websiteId)
    {
        $websiteScope = 'store';
        $defaultScope = 'default';

        $this->_configInterface->saveConfig('winman_bridge/products/full_product_update', 0, $defaultScope, 0);
        $this->_configInterface->saveConfig('winman_bridge/products/full_product_category_update', 0, $defaultScope, 0);
        $this->_configInterface->saveConfig('winman_bridge/customers/full_customer_update', 0, $defaultScope, 0);

        $this->_configInterface->saveConfig('winman_bridge/products/full_product_update', 0, $websiteScope, $websiteId);
        $this->_configInterface->saveConfig('winman_bridge/products/full_product_category_update', 0, $websiteScope, $websiteId);
        $this->_configInterface->saveConfig('winman_bridge/customers/full_customer_update', 0, $websiteScope, $websiteId);

        $this->_config->clean();
    }

    /**
     * Fetch paged product data from the WinMan REST API.
     *
     * @param integer $page
     */
    private function fetchProducts($page = 1)
    {
        $size = 10;

        $apiUrl = $this->_helper->getApiBaseUrl($this->_currentWebsite->getCode()) . '/products?website='
            . urlencode($this->_helper->getWinmanWebsite($this->_currentWebsite->getCode()))
            . '&page=' . $page . '&size=' . $size;

        $seconds = $this->_timezoneInterface->scopeTimeStamp() - $this->_lastExecutedTimestamp;

        if (!$this->_helper->getFullProductUpdate($this->_currentWebsite->getCode())) {
            $apiUrl .= '&modified=' . $seconds;
        }

        $response = $this->_helper->executeCurl($this->_currentWebsite->getCode(), $apiUrl);

        if (isset($response->Products) && count($response->Products) > 0) {
            foreach ($response->Products as $key => $product) {
                if (!$this->updateProductCatalog($product)) {
                    if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                        $this->_logger->warning(__('Could not update product') . ': ' . $product->Sku);
                    }
                    $this->_finishWithErrors = true;
                }
            }
            $page += 1;
            $this->fetchProducts($page);
        }

        if (empty($response)) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning(__('Could not retrieve product data from WinMan'));
            }
            $this->_finishWithErrors = true;
        }
    }

    /**
     * Fetch product images from the WinMan REST API for the specified product SKU.
     *
     * @param string $sku
     * @return array
     */
    private function fetchProductImages($sku)
    {
        $apiUrl = $this->_helper->getApiBaseUrl($this->_currentWebsite->getCode()) . '/productattachments?website='
            . urlencode($this->_helper->getWinmanWebsite($this->_currentWebsite->getCode()))
            . '&sku=' . urlencode($sku);

        $response = $this->_helper->executeCurl($this->_currentWebsite->getCode(), $apiUrl);

        if (isset($response->ProductAttachments) && count($response->ProductAttachments) > 0) {
            return $response->ProductAttachments[0]->Attachments;
        }

        return [];
    }

    /**
     * Retrieve stock level information from the WinMan REST API for the specified product SKU.
     *
     * @param string $sku
     */
    private function fetchStockLevels($sku)
    {
        $apiUrl = $this->_helper->getApiBaseUrl($this->_currentWebsite->getCode()) . '/productinventories?website='
            . urlencode($this->_helper->getWinmanWebsite($this->_currentWebsite->getCode()))
            . '&sku=' . urlencode($sku);

        $response = $this->_helper->executeCurl($this->_currentWebsite->getCode(), $apiUrl);

        if (isset($response->Inventories) && count($response->Inventories) > 0) {
            foreach ($response->Inventories as $inventory) {
                if (!$this->updateStock($inventory)) {
                    if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                        $this->_logger->warning(__('Could not update stock level information for product') . ': ' . $inventory->ProductSku);
                    }
                    $this->_finishWithErrors = true;
                }
            }
        }

        if (empty($response)) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning(__('Could not retrieve product stock level data from WinMan'));
            }
            $this->_finishWithErrors = true;
        }
    }

    /**
     * Fetch paged product category data from the WinMan REST API.
     *
     * @param integer $page
     */
    private function fetchCategories($page = 1)
    {
        $size = 10;

        $apiUrl = $this->_helper->getApiBaseUrl($this->_currentWebsite->getCode()) . '/productcategories?website='
            . urlencode($this->_helper->getWinmanWebsite($this->_currentWebsite->getCode()))
            . '&page=' . $page . '&size=' . $size;

        $seconds = $this->_timezoneInterface->scopeTimeStamp() - $this->_lastExecutedTimestamp;

        if (!$this->_helper->getFullProductCategoryUpdate($this->_currentWebsite->getCode())) {
            $apiUrl .= '&modified=' . $seconds;
        }

        $response = $this->_helper->executeCurl($this->_currentWebsite->getCode(), $apiUrl);

        if (isset($response->ProductCategories) && count($response->ProductCategories) > 0) {
            foreach ($response->ProductCategories as $category) {
                if (!$this->updateCategories($category)) {
                    if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                        $this->_logger->warning(__('Could not update category') . ': ' . $category->CategoryName);
                    }
                    $this->_finishWithErrors = true;
                }
            }
            $page += 1;
            $this->fetchCategories($page);
        }

        if (empty($response)) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning(__('Could not retrieve product category data from WinMan'));
            }
            $this->_finishWithErrors = true;
        }
    }

    /**
     * Fetch paged customer data from the WinMan REST API.
     *
     * @param integer $page
     */
    private function fetchCustomers($page = 1)
    {
        $size = 10;

        $apiUrl = $this->_helper->getApiBaseUrl($this->_currentWebsite->getCode()) . '/customers?website='
            . urlencode($this->_helper->getWinmanWebsite($this->_currentWebsite->getCode()))
            . '&page=' . $page . '&size=' . $size;

        $seconds = $this->_timezoneInterface->scopeTimeStamp() - $this->_lastExecutedTimestamp;

        if (!$this->_helper->getFullCustomerUpdate($this->_currentWebsite->getCode())) {
            $apiUrl .= '&modified=' . $seconds;
        }

        $response = $this->_helper->executeCurl($this->_currentWebsite->getCode(), $apiUrl);

        if (isset($response->Customers) && count($response->Customers) > 0) {
            foreach ($response->Customers as $customer) {
                if (!$this->updateCustomers($customer)) {
                    if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                        $this->_logger->warning(__('Could not update customer') . ': ' . $customer->CustomerId . '-' . $customer->Branch);
                    }
                    $this->_finishWithErrors = true;
                }
            }
            $page += 1;
            $this->fetchCustomers($page);
        }

        if (empty($response)) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning(__('Could not retrieve customer data from WinMan'));
            }
            $this->_finishWithErrors = true;
        }
    }

    /**
     * Fetch price list data from the WinMan REST API for the specified Customer GUID.
     *
     * @param string $guid
     * @return mixed
     */
    private function fetchCustomerPriceList($guid)
    {
        $apiUrl = $this->_helper->getApiBaseUrl($this->_currentWebsite->getCode()) . '/customerpricelists?website='
            . urlencode($this->_helper->getWinmanWebsite($this->_currentWebsite->getCode()))
            . '&guid=' . $guid;

        $response = $this->_helper->executeCurl($this->_currentWebsite->getCode(), $apiUrl);

        return $response;
    }

    /**
     * Update the Magento product catalog using the data from the WinMan REST API.
     *
     * @param mixed $data
     * @return boolean
     */
    private function updateProductCatalog($data)
    {
        $success = true;

        /**
         * Using _productRepository->save() at global scope forces product to be saved to all websites.
         * We don't necessarily want this, so we need to keep track of which website(s) the product belongs to.
         */
        $websiteIds = [];

        /** Find the correct tax class ID. */
        $taxClasses = $this->_taxClassCollectionFactory->create()
            ->addFieldToFilter('class_type', 'PRODUCT');

        if (!empty($data->TaxCode->TaxCodeId)) {
            $taxClasses->addFieldToFilter('class_name', $data->TaxCode->TaxCodeId);
        } else {
            $taxClasses->addFieldToFilter('class_name', 'Default');
        }

        try {
            $taxClassId = ($data->Taxable) ? $taxClasses->getFirstItem()->getId() : 0;
            $taxClassId = ($taxClassId) ? $taxClassId : 0;
        } catch (\Exception $e) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning($e->getMessage());
            }
            return false;
        }

        /** If product already exists, update it. */
        try {
            $product = $this->_productRepository->get($data->Sku);
            $websiteIds = $product->getWebsiteIds();
            $product = $this->setProductData($product, $data, $taxClassId, false);
        } catch (\Exception $e) {
            /** Otherwise, create a new one. */
            $product = $this->_productFactory->create();
            $product = $this->setProductData($product, $data, $taxClassId, true);
        }

        if (!$product) {
            return false;
        }

        $websiteIds[] = $this->_currentWebsite->getId();
        $websiteIds = array_unique($websiteIds);
        $addedWebsites = array_diff($product->getWebsiteIds(), $websiteIds);

        if ($this->_helper->getEnableProductImages($this->_currentWebsite->getCode())) {
            /** Remove existing images. */
            $mediaGalleryEntries = $product->getMediaGalleryEntries();
            foreach ($mediaGalleryEntries as $key => $entry) {
                unset($mediaGalleryEntries[$key]);
            }
            $product->setMediaGalleryEntries($mediaGalleryEntries);

            try {
                $product = $this->_productRepository->save($product);
            } catch (\Exception $e) {
                if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                    $this->_logger->warning($e->getMessage());
                }
                return false;
            }

            /** Add new / updated images. */
            $attachments = $this->fetchProductImages($data->Sku);
            foreach ($attachments as $key => $attachment) {
                if (!$this->saveProductImage($product, $attachment)) {
                    $success = false;
                }
            }
        }

        if ($this->_helper->getEnableStock($this->_currentWebsite->getCode())) {
            /** Set stock level. */
            $this->fetchStockLevels($data->Sku);
        }

        try {
            /** Remove the product from any unnecessary websites. */
            $this->_productWebsiteFactory->create()->removeProducts($addedWebsites, [$product->getId()]);
        } catch (\Exception $e) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning($e->getMessage());
            }
            return false;
        }

        return $success;
    }


    /**
     * Set and save the product data for the specified product, returning the saved product.
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param mixed $data
     * @param integer $taxClassId
     * @param boolean $isNew
     * @return boolean|\Magento\Catalog\Api\Data\ProductInterface
     */
    private function setProductData($product, $data, $taxClassId, $isNew = false)
    {
        $defaultAttributeSetId = $this->getDefaultProductAttributeSetId();

        if (!$defaultAttributeSetId) {
            return false;
        }

        /** Add product prices from Price Lists. */
        $prices = [];

        foreach ($data->ProductPriceLists as $priceList) {
            if (isset($priceList->ProductPrices[0]->PriceValue)) {
                $now = time();
                $start = strtotime($priceList->ProductPrices[0]->EffectiveDateStart);
                $end = strtotime($priceList->ProductPrices[0]->EffectiveDateEnd);

                /** Make sure a customer group exists with the same name as the price list. */
                $customerGroupId = $this->getCustomerGroupId($priceList->PriceListId);

                if (!$customerGroupId) {
                    return false;
                }

                /** Set the quantity to at least 1. */
                $quantity = ($priceList->ProductPrices[0]->Quantity <= 1) ? 1 : floatval($priceList->ProductPrices[0]->Quantity);

                if ($now < $end && $now > $start) {
                    $prices[] = [
                        'website_id' => 0,
                        'cust_group' => $customerGroupId,
                        'price_qty' => $quantity,
                        'price' => $priceList->ProductPrices[0]->PriceValue,
                    ];
                }
            }
        }

        $product
            ->setAttributeSetId($defaultAttributeSetId)
            ->setTypeId('simple')
            ->setVisibility(4)
            ->setStatus(1)
            ->setName(ucwords(strtolower($data->Name)))
            ->setWeight($data->Weight)
            ->setDescription($data->LongDescription)
            ->setShortDescription($data->ShortDescription)
            ->setMetaTitle($data->MetaTitle)
            ->setMetaKeywords($data->MetaKeywords)
            ->setMetaDescription($data->MetaDescription)
            ->setBarcode($data->Barcode)
            ->setUnitOfMeasure($data->UnitOfMeasure->MeasurePrintText)
            ->setPackSize($data->PackSize)
            ->setLength($data->Length)
            ->setWidth($data->Width)
            ->setHeight($data->Height)
            ->setTierPrice($prices);

        if (empty($data->WebPrice)) {
            $product->setPrice($data->StandardPrice);
        } else {
            $product->setPrice($data->WebPrice);
        }

        $product->setTaxClassId($taxClassId);

        if ($isNew) {
            $product->setSku($data->Sku);
        }

        if ($data->ConfigurableProduct) {
            $product->setCanSaveCustomOptions(true);
            $product->setOptions($this->createCustomOptionsArray($data->Sku, $data->ConfiguredStructureOptions));
        }

        try {
            $product = $this->_productRepository->save($product);
        } catch (\Exception $e) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning($e->getMessage());
            }
            return false;
        }

        return $product;
    }

    /**
     * Create the data array for the specified product's customisable options
     * using options data from the WinMan REST API.
     *
     * @param string $sku
     * @param array $options
     * @return array
     */
    private function createCustomOptionsArray($sku, $options)
    {
        $productOptions = [];

        foreach ($options as $key => $option) {
            $productOptions[$key] = [
                'title' => str_replace('&#34;', '"', ucwords(strtolower($option->OptionId))),
                'type' => $option->AllowMultipleSelection ? 'multiple' : 'drop_down',
                'is_require' => !$option->AllowNoSelection,
                'sort_order' => $key + 1
            ];

            foreach ($option->OptionItems as $itemKey => $item) {
                $productOptions[$key]['values'][] = [
                    'title' => str_replace('&#34;', '"', ucwords(strtolower($item->OptionItemId))),
                    'price' => $item->OptionItemPrice,
                    'price_type' => 'fixed',
                    'sku' => str_replace('&#34;', '"', strtoupper($item->OptionItemId)),
                    'sort_order' => $itemKey + 1
                ];
            }
        }

        $customOptions = [];

        foreach ($productOptions as $option) {
            $customOption = $this->_optionFactory->create(['data' => $option]);
            $customOption->setProductSku($sku);
            if (isset($option['values'])) {
                $values = [];
                foreach ($option['values'] as $value) {
                    $values[] = $this->_optionValuesFactory->create(['data' => $value]);
                }
                $customOption->setValues($values);
            }
            $customOptions[] = $customOption;
        }

        return $customOptions;
    }

    /**
     * Save the specified image for the specified product.
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param mixed $imageData
     * @return boolean
     */
    private function saveProductImage($product, $imageData)
    {
        if ($imageData->Type === 'WebImages') {

            if (!file_exists($this->_mediaPath . 'importedImages')) {
                mkdir($this->_mediaPath . 'importedImages', 0775, true);
            }

            $imagePath = 'importedImages/' . $imageData->FileName;
            $imageFile = fopen($this->_mediaPath . $imagePath, 'wb');

            fwrite($imageFile, base64_decode($imageData->Data));
            fclose($imageFile);

            $product->addImageToMediaGallery($imagePath, null, true, false);

            try {
                $product->save();
            } catch (\Exception $e) {
                if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                    $this->_logger->warning($e->getMessage());
                }
                return false;
            }

            if ($product->getThumbnail() == 'no_selection') {
                $images = $product->getMediaGalleryImages();

                foreach ($images as $image) {
                    $product->setImage($image['file']);
                    $product->setThumbnail($image['file']);
                    $product->setSmallImage($image['file']);
                    break;
                }

                try {
                    $product->save();
                } catch (\Exception $e) {
                    if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                        $this->_logger->warning($e->getMessage());
                    }
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Update product stock level information using data from the
     * WinMan REST API.
     *
     * @param mixed $inventory
     * @return boolean
     */
    private function updateStock($inventory)
    {
        $sku = $inventory->ProductSku;

        try {
            $stockItem = $this->_stockRegistry->getStockItemBySku($sku);
        } catch (\Exception $e) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning($e->getMessage());
            }
            return false;
        }

        $stock = 0;

        if (count($inventory->ProductInventories) > 0) {
            foreach ($inventory->ProductInventories as $inventory) {
                $stock += $inventory->QuantityInStock;
            }
        }

        $stockItem->setQty($stock)
            ->setManageStock(true)
            ->setUseConfigManageStock(true)
            ->setIsQtyDecimal(true)
            ->setIsInStock((boolean)$stock);

        try {
            $this->_stockRegistry->updateStockItemBySku($sku, $stockItem);
        } catch (\Exception $e) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning($e->getMessage());
            }
            return false;
        }

        return true;
    }

    /**
     * Get the ID of the customer group with the specified name.
     * If the group does not exist, create it.
     *
     * @param string|null $groupName
     * @return integer|boolean
     */
    private function getCustomerGroupId($groupName = null)
    {
        /** If group name is null, get the default customer group, else, check if group exists. */
        if (is_null($groupName)) {
            $defaultStoreId = $this->getDefaultStoreId($this->_currentWebsite);

            try {
                $groupId = $this->_groupManagementInterface
                    ->getDefaultGroup($defaultStoreId)
                    ->getId();
                return $groupId;
            } catch (\Exception $e) {
                if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                    $this->_logger->warning($e->getMessage());
                }
                return false;
            }
        }

        try {
            $groupId = $this->_customerGroupCollectionFactory
                ->create()
                ->addFieldToFilter('customer_group_code', $groupName)
                ->getFirstItem()
                ->getId();
        } catch (\Exception $e) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning($e->getMessage());
            }
            return false;
        }

        /** If the customer group does not exist, create it. */
        if (!$groupId) {
            $taxClasses = $this->_taxClassCollectionFactory->create()
                ->addFieldToFilter('class_type', 'CUSTOMER')
                ->addFieldToFilter('class_name', 'Retail Customer');

            try {
                $taxClassId = $taxClasses->getFirstItem()->getId();
                $taxClassId = ($taxClassId) ? $taxClassId : 0;
            } catch (\Exception $e) {
                if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                    $this->_logger->warning($e->getMessage());
                }
                return false;
            }

            $group = $this->_customerGroupFactory->create();
            $group->setCode($groupName)
                ->setTaxClassId($taxClassId);

            try {
                $group = $this->_customerGroupRepository->save($group);
            } catch (\Exception $e) {
                if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                    $this->_logger->warning($e->getMessage());
                }
                return false;
            }

            $groupId = $group->getId();
        }

        return $groupId;
    }

    /**
     * Update product categories with data from the WinMan REST API.
     *
     * @param mixed $data
     * @return boolean
     */
    private function updateCategories($data)
    {
        /** Save image to file. */
        $fileName = null;

        if (!empty($data->CategoryImage)) {
            if (!file_exists($this->_mediaPath . 'catalog/category')) {
                mkdir($this->_mediaPath . 'catalog/category', 0777, true);
            }

            $imageData = base64_decode($data->CategoryImage);
            $f = finfo_open();
            $mime_type = finfo_buffer($f, $imageData, FILEINFO_MIME_TYPE);
            $ext = str_replace('image/', '.', $mime_type);
            $fileName = $data->CategoryGuid . $ext;
            finfo_close($f);

            $imagePath = 'catalog/category/' . $fileName;
            $imageFile = fopen($this->_mediaPath . $imagePath, 'wb');

            fwrite($imageFile, $imageData);
            fclose($imageFile);
        }

        $defaultStoreId = $this->getDefaultStoreId($this->_currentWebsite);
        $rootId = $this->_storeManager
            ->getStore($defaultStoreId)
            ->getRootCategoryId();

        $existingCategoryId = $this->findExistingCategory(
            $data->CategoryGuid,
            $data->CategoryName,
            null,
            $data->CategoryPath);

        if ($existingCategoryId === false) {
            return false;
        }

        if ($existingCategoryId === 0) {
            /**
             * Create new category.
             * Cycle through all categories in path, creating if necessary.
             */
            $parents = explode('/', $data->CategoryPath);
            array_pop($parents);
            $parentId = $rootId;

            foreach ($parents as $key => $parent) {
                unset($parentCatId);

                try {
                    $parentCategory = $this->_categoryCollectionFactory
                        ->create()
                        ->addAttributeToFilter('name', $parent)
                        ->addAttributeToFilter('parent_id', $parentId)
                        ->getFirstitem();
                } catch (\Exception $e) {
                    if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                        $this->_logger->warning($e->getMessage());
                    }
                    return false;
                }

                try {
                    $parentCatId = $parentCategory->getId();
                } catch (\Exception $e) {
                    if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                        $this->_logger->warning($e->getMessage());
                    }
                    return false;
                }

                if (!isset($parentCatId)) {
                    /** Create the category. */
                    $newCategory = $this->_categoryFactory->create();
                    $newCategory->setUrlKey(urlencode($parent))
                        ->setParentId($parentId)
                        ->setName($parent)
                        ->setIsActive(true);

                    try {
                        $newCategory = $this->_categoryRepository->save($newCategory);
                    } catch (\Exception $e) {
                        if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                            $this->_logger->warning($e->getMessage());
                        }
                        return false;
                    }

                    $parentId = $newCategory->getId();
                } else {
                    $parentId = $parentCatId;
                    continue;
                }
            }

            $new = $this->_categoryFactory->create();

            try {
                $new->setUrlKey(urlencode($data->CategoryName))
                    ->setParentId($parentId)
                    ->setName($data->CategoryName)
                    ->setGuid($data->CategoryGuid)
                    ->setDescription($data->MetaDescription)
                    ->setMetaTitle($data->MetaTitle)
                    ->setMetaDescription($data->MetaDescription)
                    ->setMetaKeywords($data->MetaKeywords)
                    ->setImage($fileName, array('image', 'small_image', 'thumbnail'), false, false)
                    ->setIsActive(true);

                $new = $this->_categoryRepository->save($new);
            } catch (\Exception $e) {
                if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                    $this->_logger->warning($e->getMessage());
                }
                return false;
            }

            $existingCategoryId = $new->getId();
        } else if (isset($existingCategoryId)) {
            /** Update existing category. */
            try {
                $existingCategory = $this->_categoryRepository
                    ->get($existingCategoryId)
                    ->setUrlKey(urlencode($data->CategoryName))
                    ->setName($data->CategoryName)
                    ->setGuid($data->CategoryGuid)
                    ->setDescription($data->MetaDescription)
                    ->setMetaTitle($data->MetaTitle)
                    ->setMetaDescription($data->MetaDescription)
                    ->setMetaKeywords($data->MetaKeywords)
                    ->setImage($fileName, array('image', 'small_image', 'thumbnail'), false, false)
                    ->setIsActive(true);

                $this->_categoryRepository->save($existingCategory);
            } catch (\Exception $e) {
                if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                    $this->_logger->warning($e->getMessage());
                }
                return false;
            }
        }

        /** Add products to category. */
        return $this->populateCategoryProducts($data->Products, $existingCategoryId);
    }

    /**
     * Check if the specified product category already exists in Magento.
     *
     * @param string|null $guid
     * @param string|null $name
     * @param integer|null $parentId
     * @param string|null $path
     * @return integer|boolean
     */
    private function findExistingCategory($guid = null, $name = null, $parentId = null, $path = null)
    {
        $category = $this->_categoryCollectionFactory->create();

        try {
            if (!is_null($guid)) {
                $category->addAttributeToFilter('guid', $guid);
            } else if (!is_null($name) && !is_null($path)) {
                $pathArray = explode('/', $path);
                $level = count($pathArray) + 1;
                $category->addAttributeToFilter('name', $name)
                    ->addAttributeToFilter('level', $level);
            } else if (!is_null($name) && !is_null($parentId)) {
                $category->addAttributeToFilter('name', $name)
                    ->addAttributeToFilter('parent_id', $parentId);
            } else {
                return 0;
            }
        } catch (\Exception $e) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning($e->getMessage());
            }
            return false;
        }

        $category = $category->getFirstItem();

        try {
            $categoryId = $category->getId();
        } catch (\Exception $e) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning($e->getMessage());
            }
            return false;
        }

        $result = (isset($categoryId)) ? $categoryId : 0;

        if ($result === 0 && !is_null($guid) && !is_null($name) && !is_null($path)) {
            $result = $this->findExistingCategory(null, $name, null, $path);
        }

        return $result;
    }

    /**
     * Assign specified products to the specified product category.
     *
     * @param array $products
     * @param integer $categoryId
     * @return boolean
     */
    private function populateCategoryProducts($products, $categoryId)
    {
        foreach ($products as $item) {
            try {
                $product = $this->_productRepository->get($item->ProductSku);
                $categories = $product->getCategoryIds($product);

                $categories[] = $categoryId;

                $this->_categoryLinkManagement
                    ->assignProductToCategories($item->ProductSku, $categories);
            } catch (\Exception $e) {
                if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                    $this->_logger->warning($e->getMessage());
                }
                return false;
            }
        }

        return true;
    }

    /**
     * Update Magento customer information with data from the WinMan REST API.
     *
     * @param mixed $data
     * @return boolean
     */
    private function updateCustomers($data)
    {
        foreach ($data->Contacts as $contact) {
            $allowCommunication = ($contact->AllowCommunication) ? 1 : 0;

            $priceList = $this->fetchCustomerPriceList($data->Guid);

            if (isset($priceList->CustomerPriceLists[0]->PriceList->PriceListId)) {
                $priceListId = $priceList->CustomerPriceLists[0]->PriceList->PriceListId;
            } else {
                $priceListId = null;
            }

            try {
                $groupId = $this->getCustomerGroupId($priceListId);
            } catch (\Exception $e) {
                if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                    $this->_logger->warning($e->getMessage());
                }
                return false;
            }

            /** Check if the customer already exists. */
            try {
                $customer = $this->_customerRepository->get($contact->WebsiteUserName, $this->_currentWebsite->getId());

                $customer
                    ->setCustomAttribute('guid', $data->Guid)
                    ->setCustomAttribute('allow_communication', $allowCommunication)
                    ->setPrefix($contact->Title)
                    ->setFirstname($contact->FirstName)
                    ->setLastname($contact->LastName)
                    ->setDisableAutoGroupChange(1)
                    ->setTaxvat($data->TaxNumber)
                    ->setGroupId($groupId);

                $customer = $this->_customerRepository->save($customer);
            } catch (\Exception $e) {
                /** If the customer does not exist, create a new one. */
                $customer = $this->_customerFactory->create();

                $customer
                    ->setWebsiteId($this->_currentWebsite->getId())
                    ->setEmail($contact->WebsiteUserName)
                    ->setPrefix($contact->Title)
                    ->setFirstname($contact->FirstName)
                    ->setLastname($contact->LastName)
                    ->setDisableAutoGroupChange(1)
                    ->setTaxvat($data->TaxNumber)
                    ->setGroupId($groupId);

                try {
                    $this->_customerRepository->save($customer);

                    /**
                     * A bug in Magento prevents saving custom attribute data on
                     * account creation (https://github.com/magento/magento2/issues/12479).
                     * To get around bug, re-load the newly created customer and re-save.
                     */
                    $customer = $this->_customerRepository->get($contact->WebsiteUserName, $this->_currentWebsite->getId());
                    $customer->setCustomAttribute('guid', $data->Guid)
                        ->setCustomAttribute('allow_communication', $allowCommunication);

                    $customer = $this->_customerRepository->save($customer);

                    if ($this->_helper->getEmailCustomers($this->_currentWebsite->getCode())) {
                        if (!$this->sendWelcomeEmail($customer)) {
                            return false;
                        }
                    }
                } catch (\Exception $e) {
                    if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                        $this->_logger->warning($e->getMessage());
                    }
                    return false;
                }
            }

            if (!$this->updateCustomerAddresses($customer, $data, $contact)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send a welcome email to the specified customer.
     *
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @return boolean
     */
    private function sendWelcomeEmail($customer)
    {
        $defaultStoreId = $this->getDefaultStoreId($this->_currentWebsite);
        $defaultStore = $this->_storeManager->getStore($defaultStoreId);

        $template = 'customer/create_account/email_no_password_template';
        $sender = 'customer/create_account/email_identity';
        $templateParams = ['customer' => $customer, 'back_url' => '', 'store' => $defaultStore];

        $templateId = $this->_scopeConfig->getValue($template, 'store', $defaultStoreId);

        $transport = $this->_transportBuilder
            ->setTemplateIdentifier($templateId)
            ->setTemplateOptions(['area' => 'frontend', 'store' => $defaultStoreId])
            ->setTemplateVars($templateParams)
            ->setFrom($this->_scopeConfig->getValue($sender, 'store', $defaultStoreId))
            ->addTo($customer->getEmail(), $customer->getName())
            ->getTransport();

        try {
            $transport->sendMessage();
        } catch (\Exception $e) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning($e->getMessage());
            }
            return false;
        }

        return true;
    }

    /**
     * Update the specified customer's address information with data
     * from the WinMan REST API.
     *
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param mixed $data
     * @param mixed $contact
     * @return boolean
     */
    private function updateCustomerAddresses($customer, $data, $contact)
    {
        $data->City = ($data->City) ? $data->City : 'Not specified';
        $contact->PhoneNumberWork = ($contact->PhoneNumberWork) ? $contact->PhoneNumberWork : 'Not specified';

        $data->Country = $this->_countryCollection
            ->addFieldToFilter('iso3_code', $data->Country)
            ->getFirstItem()
            ->getData('iso2_code');

        $address = $this->findAddress($data, $contact, $customer->getId());

        if (!$address) {
            /** Address does not exist in Magento so add a new one. */
            $address = $this->_addressFactory->create();

            $address->setCustomerId($customer->getId())
                ->setPrefix($contact->Title)
                ->setFirstname($contact->FirstName)
                ->setLastname($contact->LastName)
                ->setStreet(array_slice(explode('&#xD;&#xA;', $data->Address), 0, 2))
                ->setCity($data->City)
                ->setPostcode($data->PostalCode)
                ->setTelephone($contact->PhoneNumberWork)
                ->setCountryId($data->Country)
                ->setIsDefaultBilling(1)
                ->setIsDefaultShipping(1);

            try {
                $this->_addressRepository->save($address);
            } catch (\Exception $e) {
                if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                    $this->_logger->warning($e->getMessage());
                }
                return false;
            }
        }

        return true;
    }

    /**
     * Check to see if the specified address already exists for the specified customer.
     * Return the address if it already exists.
     *
     * @param mixed $data
     * @param mixed $contact
     * @param integer $customerId
     * @return boolean|\Magento\Customer\Api\Data\AddressInterface
     */
    private function findAddress($data, $contact, $customerId)
    {
        $data->City = ($data->City) ? $data->City : 'Not specified';
        $contact->PhoneNumberWork = ($contact->PhoneNumberWork) ? $contact->PhoneNumberWork : 'Not specified';

        /** The street address can only contain 2 lines in Magento. Remove any additional lines. */
        $street = implode("\n", array_slice(explode('&#xD;&#xA;', $data->Address), 0, 2));

        $searchCriteria = $this->_searchCriteriaBuilder
            ->addFilter('parent_id', $customerId)
            ->addFilter('firstname', $contact->FirstName)
            ->addFilter('lastname', $contact->LastName)
            ->addFilter('street', $street)
            ->addFilter('city', $data->City)
            ->addFilter('postcode', $data->PostalCode)
            ->addFilter('telephone', $contact->PhoneNumberWork)
            ->addFilter('country_id', $data->Country)
            ->create();

        try {
            $addresses = $this->_addressRepository->getList($searchCriteria)->getItems();
        } catch (\Exception $e) {
            if ($this->_helper->getEnableLogging($this->_currentWebsite->getCode())) {
                $this->_logger->warning($e->getMessage());
            }
            return false;
        }

        if (count($addresses) > 0) {
            return $addresses[0];
        }

        return false;
    }
}
