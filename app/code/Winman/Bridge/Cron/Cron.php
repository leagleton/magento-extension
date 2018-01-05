<?php

namespace Winman\Bridge\Cron;

use \Winman\Bridge\Logger\Logger;
use \Winman\Bridge\Helper\Data;
use \Magento\Framework\App\ObjectManager;
use \Magento\Catalog\Model\Product;
use \Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use \Magento\Catalog\Model\ProductFactory;
use \Magento\Catalog\Model\ProductRepository;
use \Magento\Catalog\Model\Product\Action as ProductAction;
use \Magento\Catalog\Api\Data\ProductAttributeInterface;
use \Magento\CatalogInventory\Api\StockRegistryInterface;
use \Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\Framework\Filesystem;
use \Magento\Catalog\Model\Category;
use \Magento\Catalog\Model\CategoryFactory;
use \Magento\Catalog\Model\CategoryRepository;
use \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use \Magento\Catalog\Api\CategoryLinkManagementInterface;
use \Magento\Customer\Model\Customer as CustomerModel;
use \Magento\Customer\Model\CustomerFactory as CustomerFactory;
use \Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use \Magento\Customer\Model\ResourceModel\Group\CollectionFactory as CustomerGroupCollectionFactory;
use \Magento\Customer\Api\GroupRepositoryInterface as CustomerGroupRepository;
use \Magento\Customer\Api\Data\GroupInterfaceFactory as CustomerGroupFactory;
use \Magento\Framework\Mail\Template\TransportBuilder;
use \Magento\Customer\Api\GroupManagementInterface;
use \Magento\Customer\Model\AddressFactory;
use \Magento\Customer\Api\AddressRepositoryInterface as AddressRepository;
use \Magento\Directory\Model\ResourceModel\Country\Collection as CountryCollection;
use \Magento\Store\Model\StoreRepository;
use \Magento\Store\Model\StoreManager;
use \Magento\Tax\Model\TaxClass\Factory as TaxClassFactory;
use \Magento\Tax\Model\TaxClass\Repository as TaxClassRepository;
use \Magento\Tax\Model\ResourceModel\TaxClass\Collection as TaxClassCollection;
use \Magento\Tax\Model\ResourceModel\TaxClass\CollectionFactory as TaxClassCollectionFactory;
use \Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use \Magento\Framework\App\ResourceConnection;
use \Magento\Eav\Model\Config as EavConfig;
use \Magento\Framework\App\Config;
use \Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use \Magento\Framework\Api\FilterBuilder;
use \Magento\Framework\Api\SearchCriteriaBuilder;
use \Magento\Framework\App\Area;
use \Magento\Store\Model\ScopeInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use \Magento\Catalog\Model\Product\WebsiteFactory AS ProductWebsiteFactory;

/**
 * Class Cron
 * @package Winman\Bridge\Cron
 */
class Cron
{
    private $_ACCESS_TOKEN;
    private $_API_BASEURL;
    private $_WINMAN_WEBSITE;
    private $_CURL_HEADERS;
    private $_ENABLED;
    private $_ENABLE_LOGGING;

    private $_ENABLE_PRODUCTS;
    private $_ENABLE_STOCK;
    private $_ENABLE_IMAGES;
    private $_ENABLE_CATEGORIES;
    private $_ENABLE_CUSTOMERS;
    private $_EMAIL_CUSTOMERS;

    private $_FULL_PRODUCT_UPDATE;
    private $_FULL_CATEGORY_UPDATE;
    private $_FULL_CUSTOMER_UPDATE;

    protected $_logger;
    protected $_helper;
    protected $_objectManager;
    protected $_productModel;
    protected $_productResource;
    protected $_productFactory;
    protected $_productRepository;
    protected $_productAction;
    protected $_stockRegistry;

    protected $_categoryModel;
    protected $_categoryFactory;
    protected $_categoryRepository;
    protected $_categoryCollectionFactory;
    protected $_categoryLinkManagement;

    protected $_customerModel;
    protected $_customerFactory;
    protected $_customerRepository;
    protected $_customerGroupCollectionFactory;
    protected $_customerGroupRepository;
    protected $_customerGroupFactory;
    protected $_transportBuilder;
    protected $_groupManagementInterface;
    protected $_addressFactory;
    protected $_addressRepository;
    protected $_countryCollection;

    protected $_storeRepository;
    protected $_storeManager;

    protected $_taxClassFactory;
    protected $_taxClassRepository;
    protected $_taxClassCollection;
    protected $_taxClassCollectionFactory;

    protected $_timezoneInterface;

    protected $_fileSystem;
    protected $_resourceConnection;
    protected $_eavConfig;
    protected $_config;
    protected $_configInterface;
    protected $_filterBuilder;
    protected $_searchCriteriaBuilder;

    protected $_scopeConfig;
    protected $_productWebsiteFactory;

    private $_mediaPath;
    private $_lastExecutedTimestamp;
    private $_websites;
    private $_currentWebsite;

    /**
     * Cron constructor.
     * @param Logger $logger
     * @param Data $helper
     * @param Product $productModel
     * @param ProductResource $productResource
     * @param ProductFactory $productFactory
     * @param ProductRepository $productRepository
     * @param ProductAction $productAction
     * @param StockRegistryInterface $stockRegistry
     * @param Category $categoryModel
     * @param CategoryFactory $categoryFactory
     * @param CategoryRepository $categoryRepository
     * @param CollectionFactory $categoryCollectionFactory
     * @param CategoryLinkManagementInterface $categoryLinkManagement
     * @param CustomerModel $customerModel
     * @param CustomerFactory $customerFactory
     * @param CustomerRepository $customerRepository
     * @param CustomerGroupCollectionFactory $customerGroupCollectionFactory
     * @param CustomerGroupRepository $customerGroupRepository
     * @param CustomerGroupFactory $customerGroupFactory
     * @param TransportBuilder $transportBuilder
     * @param GroupManagementInterface $groupManagementInterface
     * @param AddressFactory $addressFactory
     * @param AddressRepository $addressRepository
     * @param CountryCollection $countryCollection
     * @param StoreRepository $storeRepository
     * @param StoreManager $storeManager
     * @param TaxClassFactory $taxClassFactory
     * @param TaxClassRepository $taxClassRepository
     * @param TaxClassCollection $taxClassCollection
     * @param TaxClassCollectionFactory $taxClassCollectionFactory
     * @param TimezoneInterface $timezoneInterface
     * @param Filesystem $fileSystem
     * @param ResourceConnection $resourceConnection
     * @param EavConfig $eavConfig
     * @param Config $config
     * @param ConfigInterface $configInterface
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ScopeConfig $scopeConfig
     * @param ProductWebsiteFactory $productWebsiteFactory
     */
    public function __construct(
        Logger $logger,
        Data $helper,
        Product $productModel,
        ProductResource $productResource,
        ProductFactory $productFactory,
        ProductRepository $productRepository,
        ProductAction $productAction,
        StockRegistryInterface $stockRegistry,
        Category $categoryModel,
        CategoryFactory $categoryFactory,
        CategoryRepository $categoryRepository,
        CollectionFactory $categoryCollectionFactory,
        CategoryLinkManagementInterface $categoryLinkManagement,
        CustomerModel $customerModel,
        CustomerFactory $customerFactory,
        CustomerRepository $customerRepository,
        CustomerGroupCollectionFactory $customerGroupCollectionFactory,
        CustomerGroupRepository $customerGroupRepository,
        CustomerGroupFactory $customerGroupFactory,
        TransportBuilder $transportBuilder,
        GroupManagementInterface $groupManagementInterface,
        AddressFactory $addressFactory,
        AddressRepository $addressRepository,
        CountryCollection $countryCollection,
        StoreRepository $storeRepository,
        StoreManager $storeManager,
        TaxClassFactory $taxClassFactory,
        TaxClassRepository $taxClassRepository,
        TaxClassCollection $taxClassCollection,
        TaxClassCollectionFactory $taxClassCollectionFactory,
        TimezoneInterface $timezoneInterface,
        Filesystem $fileSystem,
        ResourceConnection $resourceConnection,
        EavConfig $eavConfig,
        Config $config,
        ConfigInterface $configInterface,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ScopeConfig $scopeConfig,
        ProductWebsiteFactory $productWebsiteFactory)
    {
        $this->_logger = $logger;
        $this->_helper = $helper;
        $this->_objectManager = ObjectManager::getInstance();
        $this->_productModel = $productModel;
        $this->_productResource = $productResource;
        $this->_productFactory = $productFactory;
        $this->_productRepository = $productRepository;
        $this->_productAction = $productAction;
        $this->_stockRegistry = $stockRegistry;

        $this->_categoryModel = $categoryModel;
        $this->_categoryFactory = $categoryFactory;
        $this->_categoryRepository = $categoryRepository;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_categoryLinkManagement = $categoryLinkManagement;

        $this->_customerModel = $customerModel;
        $this->_customerFactory = $customerFactory;
        $this->_customerRepository = $customerRepository;
        $this->_customerGroupCollectionFactory = $customerGroupCollectionFactory;
        $this->_customerGroupRepository = $customerGroupRepository;
        $this->_customerGroupFactory = $customerGroupFactory;
        $this->_transportBuilder = $transportBuilder;
        $this->_groupManagementInterface = $groupManagementInterface;
        $this->_addressFactory = $addressFactory;
        $this->_addressRepository = $addressRepository;
        $this->_countryCollection = $countryCollection;

        $this->_storeRepository = $storeRepository;
        $this->_storeManager = $storeManager;

        $this->_taxClassFactory = $taxClassFactory;
        $this->_taxClassRepository = $taxClassRepository;
        $this->_taxClassCollection = $taxClassCollection;
        $this->_taxClassCollectionFactory = $taxClassCollectionFactory;

        $this->_timezoneInterface = $timezoneInterface;

        $this->_fileSystem = $fileSystem;
        $this->_resourceConnection = $resourceConnection;
        $this->_eavConfig = $eavConfig;
        $this->_config = $config;
        $this->_configInterface = $configInterface;
        $this->_filterBuilder = $filterBuilder;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;

        $this->_scopeConfig = $scopeConfig;
        $this->_productWebsiteFactory = $productWebsiteFactory;

        $this->_mediaPath = $this->_fileSystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
        $this->_lastExecutedTimestamp = $this->getLastExecutedTimestamp();

        $this->_websites = $this->_storeManager->getWebsites(false, true);
    }

    /**
     * @return $this
     */
    public function execute()
    {
        $websites = $this->_websites;

        foreach ($websites as $code => $website) {
            if ($this->getDefaultStoreId($website)) {
                $this->getConfigSettings($code);
                $this->_currentWebsite = $website;

                if ($this->_ENABLED) {
                    if ($this->_ENABLE_LOGGING) {
                        $this->_logger->info(__('WinMan synchronisation started for website: ') . $website->getName() . '.');
                    }

                    if ($this->_ENABLE_PRODUCTS) {
                        $this->fetchProducts();
                    }

                    if ($this->_ENABLE_CATEGORIES) {
                        $this->fetchCategories();
                    }

                    if ($this->_ENABLE_CUSTOMERS) {
                        $this->fetchCustomers();
                    }

                    $this->disableFullUpdates($website->getid());

                    if ($this->_ENABLE_LOGGING) {
                        $this->_logger->info(__('WinMan synchronisation finished for website: ') . $website->getName() . '.');
                    }
                }
            }
        }
        return $this;
    }

    /**
     * @param $websiteCode
     */
    private function getConfigSettings($websiteCode)
    {
        $this->_WINMAN_WEBSITE = $this->_helper->getconfig('winman_bridge/general/winman_website', $websiteCode);
        $this->_API_BASEURL = $this->_helper->getconfig('winman_bridge/general/api_baseurl', $websiteCode);
        $this->_ACCESS_TOKEN = $this->_helper->getconfig('winman_bridge/general/access_token', $websiteCode);
        $this->_ENABLED = $this->_helper->getconfig('winman_bridge/general/enable', $websiteCode);
        $this->_ENABLE_LOGGING = $this->_helper->getconfig('winman_bridge/general/enable_logging', $websiteCode);

        $this->_ENABLE_PRODUCTS = $this->_helper->getconfig('winman_bridge/products/enable_products', $websiteCode);
        $this->_ENABLE_STOCK = $this->_helper->getconfig('winman_bridge/products/enable_stock', $websiteCode);
        $this->_ENABLE_IMAGES = $this->_helper->getconfig('winman_bridge/products/enable_product_images', $websiteCode);
        $this->_ENABLE_CATEGORIES = $this->_helper->getconfig('winman_bridge/products/enable_product_categories', $websiteCode);
        $this->_ENABLE_CUSTOMERS = $this->_helper->getconfig('winman_bridge/customers/enable_customers', $websiteCode);
        $this->_EMAIL_CUSTOMERS = $this->_helper->getconfig('winman_bridge/customers/email_customers', $websiteCode);

        $this->_FULL_PRODUCT_UPDATE = $this->_helper->getconfig('winman_bridge/products/full_product_update', $websiteCode);
        $this->_FULL_CATEGORY_UPDATE = $this->_helper->getconfig('winman_bridge/products/full_product_category_update', $websiteCode);
        $this->_FULL_CUSTOMER_UPDATE = $this->_helper->getconfig('winman_bridge/customers/full_customer_update', $websiteCode);

        $headers = array();
        $headers[] = 'accept: application/json';
        $headers[] = 'authorization: Bearer ' . $this->_ACCESS_TOKEN;

        $this->_CURL_HEADERS = $headers;
    }

    /**
     * @param $website
     * @return bool
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
     * @return null|string
     */
    private function getDefaultProductAttributeSetId()
    {
        return $this->_eavConfig
            ->getEntityType(ProductAttributeInterface::ENTITY_TYPE_CODE)
            ->getDefaultAttributeSetId();
    }

    /**
     * @return false|int
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
     * @param $websiteId
     */
    private function disableFullUpdates($websiteId)
    {
        $websiteScope = ScopeInterface::SCOPE_WEBSITES;
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
     * @param $apiUrl
     * @return mixed
     */
    private function executeCurl($apiUrl)
    {
        $curl = curl_init($apiUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->_CURL_HEADERS);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        if ($this->_ENABLE_LOGGING && !$response) {
            $this->_logger->critical('Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl));
        }

        $decoded = json_decode($response);
        curl_close($curl);

        return $decoded;
    }

    /**
     * @param int $page
     */
    private function fetchProducts($page = 1)
    {
        $size = 10;

        $apiUrl = $this->_API_BASEURL . '/products?website='
            . urlencode($this->_WINMAN_WEBSITE)
            . '&page=' . $page . '&size=' . $size;

        $seconds = $this->_timezoneInterface->scopeTimeStamp() - $this->_lastExecutedTimestamp;

        if (!$this->_FULL_PRODUCT_UPDATE) {
            $apiUrl .= '&modified=' . $seconds;
        }

        $response = $this->executeCurl($apiUrl);

        if (count($response->Products) > 0) {
            foreach ($response->Products as $key => $product) {
                $this->updateProductCatalog($product);
            }
            $page += 1;
            $this->fetchProducts($page);
        }
    }

    /**
     * @param $sku
     * @return array
     */
    private function fetchProductImages($sku)
    {
        $apiUrl = $this->_API_BASEURL . '/productattachments?website='
            . urlencode($this->_WINMAN_WEBSITE)
            . '&sku=' . urlencode($sku);

        $response = $this->executeCurl($apiUrl);

        if (count($response->ProductAttachments) > 0) {
            return $response->ProductAttachments[0]->Attachments;
        }

        return [];
    }

    /**
     * @param $sku
     */
    private function fetchStockLevels($sku)
    {
        $apiUrl = $this->_API_BASEURL . '/productinventories?website='
            . urlencode($this->_WINMAN_WEBSITE)
            . '&sku=' . urlencode($sku);

        $response = $this->executeCurl($apiUrl);

        if (count($response->Inventories) > 0) {
            foreach ($response->Inventories as $inventory) {
                $this->updateStock($inventory);
            }
        }
    }

    /**
     * @param int $page
     */
    private function fetchCategories($page = 1)
    {
        $size = 10;

        $apiUrl = $this->_API_BASEURL . '/productcategories?website='
            . urlencode($this->_WINMAN_WEBSITE)
            . '&page=' . $page . '&size=' . $size;

        $seconds = $this->_timezoneInterface->scopeTimeStamp() - $this->_lastExecutedTimestamp;

        if (!$this->_FULL_CATEGORY_UPDATE) {
            $apiUrl .= '&modified=' . $seconds;
        }

        $response = $this->executeCurl($apiUrl);

        if (count($response->ProductCategories) > 0) {
            foreach ($response->ProductCategories as $category) {
                $this->updateCategories($category);
            }
            $page += 1;
            $this->fetchCategories($page);
        }
    }

    /**
     * @param int $page
     */
    private function fetchCustomers($page = 1)
    {
        $size = 10;

        $apiUrl = $this->_API_BASEURL . '/customers?website='
            . urlencode($this->_WINMAN_WEBSITE)
            . '&page=' . $page . '&size=' . $size;

        $seconds = $this->_timezoneInterface->scopeTimeStamp() - $this->_lastExecutedTimestamp;

        if (!$this->_FULL_CUSTOMER_UPDATE) {
            $apiUrl .= '&modified=' . $seconds;
        }

        $response = $this->executeCurl($apiUrl);

        if (count($response->Customers) > 0) {
            foreach ($response->Customers as $customer) {
                $this->updateCustomers($customer);
            }
            $page += 1;
            $this->fetchCustomers($page);
        }
    }

    /**
     * @param $guid
     * @return mixed
     */
    private function fetchCustomerPriceList($guid)
    {
        $apiUrl = $this->_API_BASEURL . '/customerpricelists?website='
            . urlencode($this->_WINMAN_WEBSITE)
            . '&guid=' . $guid;

        $response = $this->executeCurl($apiUrl);

        return $response;
    }

    /**
     * @param $data
     */
    private function updateProductCatalog($data)
    {
        $stores = $this->_storeRepository->getList();

        // Using _productRepository->save() at global scope forces product to be saved to all websites.
        // We don't necessarily want this, so we need to keep track of which website(s) the product belongs to.
        $websiteIds = [];

        // Find the correct tax class ID.
        $taxClasses = $this->_taxClassCollectionFactory->create()
            ->addFieldToFilter('class_type', 'PRODUCT');

        if (!empty($data->TaxCode->TaxCodeId)) {
            $taxClasses->addFieldToFilter('class_name', $data->TaxCode->TaxCodeId);
        } else {
            $taxClasses->addFieldToFilter('class_name', 'Default');
        }

        $taxClassId = ($data->Taxable) ? $taxClasses->getFirstItem()->getId() : 0;
        $taxClassId = ($taxClassId) ? $taxClassId : 0;

        // If product already exists, update it.
        if ($this->_productModel->getIdBySku($data->Sku)) {
            $product = $this->_productRepository->get($data->Sku);
            $websiteIds = $product->getWebsiteIds();
            $product = $this->setProductData($product, $data, $taxClassId, false);
        } else { // Otherwise, create a new one.
            $product = $this->_productFactory->create();
            $product = $this->setProductData($product, $data, $taxClassId, true);
        }

        $websiteIds[] = $this->_currentWebsite->getId();
        $websiteIds = array_unique($websiteIds);
        $addedWebsites = array_diff($product->getWebsiteIds(), $websiteIds);

        if ($this->_ENABLE_IMAGES) {
            // Remove existing images.
            $mediaGalleryEntries = $product->getMediaGalleryEntries();
            foreach ($mediaGalleryEntries as $key => $entry) {
                unset($mediaGalleryEntries[$key]);
            }
            $product->setMediaGalleryEntries($mediaGalleryEntries);

            try {
                $product = $this->_productRepository->save($product);
            } catch (\Exception $e) {
                if ($this->_ENABLE_LOGGING) {
                    $this->_logger->critical($e->getMessage());
                }
            }

            // Add new / updated images.
            $attachments = $this->fetchProductImages($data->Sku);
            foreach ($attachments as $key => $attachment) {
                $this->saveProductImage($product, $attachment);
            }
        }

        if ($this->_ENABLE_STOCK) {
            // Set stock level.
            $this->fetchStockLevels($data->Sku);
        }

        try {
            // Remove the product from any unnecessary websites.
            $this->_productWebsiteFactory->create()->removeProducts($addedWebsites, [$product->getId()]);
        } catch (\Exception $e) {
            if ($this->_ENABLE_LOGGING) {
                $this->_logger->critical($e->getMessage());
            }
        }
    }

    /**
     * @param $product
     * @param $data
     * @param $taxClassId
     * @param bool $isNew
     * @return \Magento\Catalog\Api\Data\ProductInterface|mixed
     */
    private function setProductData($product, $data, $taxClassId, $isNew = false)
    {
        $defaultAttributeSetId = $this->getDefaultProductAttributeSetId();

        // Add product prices from Price Lists.
        $prices = [];

        foreach ($data->ProductPriceLists as $priceList) {
            if (isset($priceList->ProductPrices[0]->PriceValue)) {
                $now = time();
                $start = strtotime($priceList->ProductPrices[0]->EffectiveDateStart);
                $end = strtotime($priceList->ProductPrices[0]->EffectiveDateEnd);

                // Make sure a customer group exists with the same name as the price list.
                $customerGroupId = $this->getCustomerGroupId($priceList->PriceListId);

                // Set the quantity to at least 1.
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
            ->setTypeId(Product\Type::TYPE_SIMPLE)
            ->setVisibility(Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setName($data->Name)
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

        try {
            $product = $this->_productRepository->save($product);
        } catch (\Exception $e) {
            if ($this->_ENABLE_LOGGING) {
                $this->_logger->critical($e->getMessage());
            }
        }

        return $product;
    }

    /**
     * @param $product
     * @param $imageData
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
                if ($this->_ENABLE_LOGGING) {
                    $this->_logger->critical($e->getMessage());
                }
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
                    if ($this->_ENABLE_LOGGING) {
                        $this->_logger->critical($e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * @param $inventory
     */
    private function updateStock($inventory)
    {
        $sku = $inventory->ProductSku;
        $stockItem = $this->_stockRegistry->getStockItemBySku($sku);
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
            ->setIsInStock((bool)$stock);

        try {
            $this->_stockRegistry->updateStockItemBySku($sku, $stockItem);
        } catch (\Exception $e) {
            if ($this->_ENABLE_LOGGING) {
                $this->_logger->critical($e->getMessage());
            }
        }
    }

    /**
     * @param null $groupName
     * @return int|null
     */
    private function getCustomerGroupId($groupName = null)
    {
        // If group name is null, get the default customer group, else, check if group exists.
        if (is_null($groupName)) {
            $defaultStoreId = $this->getDefaultStoreId($this->_currentWebsite);
            $groupId = $this->_groupManagementInterface
                ->getDefaultGroup($defaultStoreId)
                ->getId();

            return $groupId;
        }

        $groupId = $this->_customerGroupCollectionFactory
            ->create()
            ->addFieldToFilter('customer_group_code', $groupName)
            ->getFirstItem()
            ->getId();

        // If the customer group does not exist, create it.
        if (!$groupId) {
            $taxClasses = $this->_taxClassCollectionFactory->create()
                ->addFieldToFilter('class_type', 'CUSTOMER')
                ->addFieldToFilter('class_name', 'Retail Customer');

            $taxClassId = $taxClasses->getFirstItem()->getId();
            $taxClassId = ($taxClassId) ? $taxClassId : 0;

            $group = $this->_customerGroupFactory->create();
            $group->setCode($groupName)
                ->setTaxClassId($taxClassId);

            $group = $this->_customerGroupRepository->save($group);

            $groupId = $group->getId();
        }

        return $groupId;
    }

    /**
     * @param $data
     */
    private function updateCategories($data)
    {
        // Save image to file
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

        if ($existingCategoryId === 0) {
            // Create new category.
            // Cycle through all categories in path, creating if necessary.

            $parents = explode('/', $data->CategoryPath);
            array_pop($parents);
            $parentId = $rootId;

            foreach ($parents as $key => $parent) {
                unset($parentCatId);

                $parentCategory = $this->_categoryCollectionFactory
                    ->create()
                    ->addAttributeToFilter('name', $parent)
                    ->addAttributeToFilter('parent_id', $parentId)
                    ->getFirstitem();

                $parentCatId = $parentCategory->getId();
                if (!isset($parentCatId)) {
                    // Create the category.
                    $newCategory = $this->_categoryFactory->create();
                    $newCategory->setUrlKey(urlencode($parent))
                        ->setParentId($parentId)
                        ->setName($parent)
                        ->setIsActive(true);

                    try {
                        $newCategory = $this->_categoryRepository->save($newCategory);
                    } catch (\Exception $e) {
                        if ($this->_ENABLE_LOGGING) {
                            $this->_logger->critical($e->getMessage());
                        }
                    }

                    $parentId = $newCategory->getId();
                } else {
                    $parentId = $parentCatId;
                    continue;
                }
            }

            $new = $this->_categoryFactory->create();

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

            try {
                $new = $this->_categoryRepository->save($new);
            } catch (\Exception $e) {
                if ($this->_ENABLE_LOGGING) {
                    $this->_logger->critical($e->getMessage());
                }
            }

            $existingCategoryId = $new->getId();
        } else if (isset($existingCategoryId)) {
            // Update existing category.

            // TODO: check path is correct. If not, correct it.
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

            try {
                $this->_categoryRepository->save($existingCategory);
            } catch (\Exception $e) {
                if ($this->_ENABLE_LOGGING) {
                    $this->_logger->critical($e->getMessage());
                }
            }
        }

        // Add products to category.
        if (isset($existingCategoryId)) {
            $this->populateCategoryProducts($data->Products, $existingCategoryId);
        }

        // TODO: remove products that are no longer in the category in WinMan.
        // TODO: remove categories that no longer exist in WinMan.
    }

    /**
     * @param string|null $guid
     * @param string|null $name
     * @param int|null $parentId
     * @param string|null $path
     * @return int
     */
    private function findExistingCategory(string $guid = null, string $name = null, int $parentId = null, string $path = null)
    {
        $category = $this->_categoryCollectionFactory->create();

        if (!is_null($guid)) {
            $category->addAttributeToFilter('guid', $guid);
        } else if (!is_null($name) && !is_null($path)) {
            $pathArray = explode('/', $path);
            $level = count($pathArray) + 1; // TODO: should level be level of root category for default store + 1?
            $category->addAttributeToFilter('name', $name)
                ->addAttributeToFilter('level', $level);
        } else if (!is_null($name) && !is_null($parentId)) {
            $category->addAttributeToFilter('name', $name)
                ->addAttributeToFilter('parent_id', $parentId);
        } else {
            return 0;
        }

        $category = $category->getFirstItem();
        $categoryId = $category->getId();

        $result = (isset($categoryId)) ? $categoryId : 0;

        if ($result === 0 && !is_null($guid) && !is_null($name) && !is_null($path)) {
            $result = $this->findExistingCategory(null, $name, null, $path);
        }
        return $result; // return category ID (int), or 0 if category does not exist.
    }

    /**
     * @param array $products
     * @param int $categoryId
     */
    private function populateCategoryProducts(array $products, int $categoryId)
    {
        foreach ($products as $item) {
            $product = $this->_productRepository->get($item->ProductSku);
            $categories = $this->_productResource->getCategoryIds($product);

            $categories[] = $categoryId;

            try {
                $this->_categoryLinkManagement
                    ->assignProductToCategories($item->ProductSku, $categories);
            } catch (\Exception $e) {
                if ($this->_ENABLE_LOGGING) {
                    $this->_logger->notice($e->getMessage());
                }
            }
        }
    }

    /**
     * @param $data
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

            $groupId = $this->getCustomerGroupId($priceListId);

            // Check if the customer already exists.
            if ($this->_customerModel->setWebsiteId($this->_currentWebsite->getId())->loadByEmail($contact->WebsiteUserName)->getId()) {
                // If the customer exists, update their details.
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
                try {
                    $customer = $this->_customerRepository->save($customer);
                } catch (\Exception $e) {
                    if ($this->_ENABLE_LOGGING) {
                        $this->_logger->alert($e->getMessage());
                    }
                }
            } else {
                // If the customer does not exist, create a new one.
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
                    $newCustomer = $customer->save();

                    // A bug in Magento prevents saving custom attribute data on
                    // account creation (https://github.com/magento/magento2/issues/12479).
                    // To get around bug, re-load the newly created customer and re-save.
                    $customer = $this->_customerRepository->get($contact->WebsiteUserName, $this->_currentWebsite->getId());
                    $customer->setCustomAttribute('guid', $data->Guid)
                        ->setCustomAttribute('allow_communication', $allowCommunication);

                    $customer = $this->_customerRepository->save($customer);

                    if ($this->_EMAIL_CUSTOMERS) {
                        $this->sendWelcomeEmail($newCustomer);
                    }
                } catch (\Exception $e) {
                    if ($this->_ENABLE_LOGGING) {
                        $this->_logger->alert($e->getMessage());
                    }
                }
            }

            $this->updateCustomerAddresses($customer, $data, $contact);
        }
    }

    /**
     * @param $customer
     */
    private function sendWelcomeEmail($customer)
    {
        $defaultStoreId = $this->getDefaultStoreId($this->_currentWebsite);
        $defaultStore = $this->_storeManager->getStore($defaultStoreId);

        $template = 'customer/create_account/email_no_password_template';
        $sender = 'customer/create_account/email_identity';
        $templateParams = ['customer' => $customer, 'back_url' => '', 'store' => $defaultStore];

        $templateId = $this->_scopeConfig->getValue($template, ScopeInterface::SCOPE_STORE, $defaultStoreId);

        $transport = $this->_transportBuilder
            ->setTemplateIdentifier($templateId)
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $defaultStoreId])
            ->setTemplateVars($templateParams)
            ->setFrom($this->_scopeConfig->getValue($sender, ScopeInterface::SCOPE_STORE, $defaultStoreId))
            ->addTo($customer->getEmail(), $customer->getName())
            ->getTransport();

        try {
            $transport->sendMessage();
        } catch (\Exception $e) {
            if ($this->_ENABLE_LOGGING) {
                $this->_logger->alert($e->getMessage());
            }
        }
    }

    /**
     * @param $customer
     * @param $data
     * @param $contact
     */
    private function updateCustomerAddresses($customer, $data, $contact)
    {
        $data->City = ($data->City) ? $data->City : 'Not specified';
        $data->Region = ($data->Region) ? $data->Region : 'Not specified';
        $contact->PhoneNumberWork = ($contact->PhoneNumberWork) ? $contact->PhoneNumberWork : 'Not specified';

        $data->Country = $this->_countryCollection
            ->addFieldToFilter('iso3_code', $data->Country)
            ->getFirstItem()
            ->getData('iso2_code');

        $address = $this->findAddress($data, $contact, $customer->getId());

        if (!$address) {
            // Address does not exist in Magento so add a new one.
            $address = $this->_addressFactory->create();

            $address->setCustomerId($customer->getId())
                ->setPrefix($contact->Title)
                ->setFirstname($contact->FirstName)
                ->setLastname($contact->LastName)
                ->setStreet($data->Address)
                ->setCity($data->City)
                ->setRegion($data->Region)
                ->setPostcode($data->PostalCode)
                ->setTelephone($contact->PhoneNumberWork)
                ->setCountryId($data->Country)
                ->setIsDefaultBilling(1)
                ->setIsDefaultShipping(1)
                ->setSaveInAddressBook(1);

            try {
                $address->save();
            } catch (\Exception $e) {
                if ($this->_ENABLE_LOGGING) {
                    $this->_logger->alert($e->getMessage());
                }
            }
        }
    }

    /**
     * @param $data
     * @param $contact
     * @param $customerId
     * @return bool|\Magento\Customer\Api\Data\AddressInterface
     */
    private function findAddress($data, $contact, $customerId)
    {
        $searchCriteria = $this->_searchCriteriaBuilder
            ->addFilter('parent_id', $customerId)
            ->addFilter('firstname', $contact->FirstName)
            ->addFilter('lastname', $contact->LastName)
            ->addFilter('street', $data->Address)
            ->addFilter('city', $data->City)
            ->addFilter('region', $data->Region)
            ->addFilter('postcode', $data->PostalCode)
            ->addFilter('telephone', $contact->PhoneNumberWork)
            ->addFilter('country_id', $data->Country)
            ->create();

        $addresses = $this->_addressRepository->getList($searchCriteria)->getItems();

        if (count($addresses) > 0) {
            return $addresses[0];
        }

        return false;
    }
}
