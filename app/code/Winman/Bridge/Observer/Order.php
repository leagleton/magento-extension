<?php
/**
 * @author Lynn Eagleton <support@winman.com>
 */

namespace Winman\Bridge\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;
use \Winman\Bridge\Logger\Logger;
use \Winman\Bridge\Helper\Data;
use \Magento\Store\Model\StoreManager;
use \Magento\Sales\Model\OrderRepository;
use \Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use \Magento\Customer\Api\AddressRepositoryInterface as AddressRepository;
use \Magento\Directory\Model\CountryFactory;
use \Magento\Framework\Notification\NotifierInterface as Notifier;

/**
 * Class Order
 *
 * @package Winman\Bridge\Observer
 */
class Order implements ObserverInterface
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
     * @var \Magento\Store\Model\StoreManager
     */
    protected $_storeManager;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $_customerRepository;

    /**
     * @var \Magento\Customer\Api\AddressRepositoryInterface
     */
    protected $_addressRepository;

    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    protected $_countryFactory;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Framework\Notification\NotifierInterface
     */
    protected $_notifier;

    /**
     * @var \Magento\Store\Api\Data\WebsiteInterface
     */
    private $_currentWebsite;

    /**
     * Order constructor.
     *
     * @param \Winman\Bridge\Logger\Logger $logger
     * @param \Winman\Bridge\Helper\Data $helper
     * @param \Magento\Sales\Model\OrderRepository $orderRepository
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Customer\Api\AddressRepositoryInterface $addressRepository
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Magento\Store\Model\StoreManager $storeManager
     * @param \Magento\Framework\Notification\NotifierInterface $notifier
     */
    public function __construct(
        Logger $logger,
        Data $helper,
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        AddressRepository $addressRepository,
        CountryFactory $countryFactory,
        StoreManager $storeManager,
        Notifier $notifier)
    {
        $this->_logger = $logger;
        $this->_helper = $helper;

        $this->_orderRepository = $orderRepository;
        $this->_customerRepository = $customerRepository;
        $this->_addressRepository = $addressRepository;
        $this->_countryFactory = $countryFactory;

        $this->_storeManager = $storeManager;
        $this->_notifier = $notifier;

        $this->_currentWebsite = $this->_storeManager->getStore()->getWebsite();
    }

    /**
     * Execute the observer.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(Observer $observer)
    {
        if ($this->_helper->getEnabled($this->_currentWebsite->getCode()) && $this->_helper->getEnableSalesOrders($this->_currentWebsite->getCode())) {
            try {
                $order_ids = $observer->getEvent()->getOrderIds();
                $order_id = $order_ids[0];
                $order = $this->_orderRepository->get($order_id);
                $this->postRequest($order);
            } catch (\Exception $e) {
                $this->_logger->warning($e->getMessage());

                $this->_notifier->addMajor(
                    __('Order could not be placed in WinMan'),
                    __('Order could not be placed in WinMan. Please check exception logs for more information.')
                );
            }
        }
    }

    /**
     * Post the specified order data to the WinMan REST API.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     */
    public function postRequest($order)
    {
        $apiUrl = $this->_helper->getApiBaseUrl($this->_currentWebsite->getCode()) . '/salesorders';

        $orderItems = $order->getAllItems();
        $orderData = $order->getData();
        $isGuest = $orderData['customer_is_guest'];
        $guid = ($isGuest) ? null : $this->getCustomerGuid($orderData['customer_email']);
        $shipping = $this->getAddress($order->getShippingAddress());
        $billing = $this->getAddress($order->getBillingAddress(), 'billing');

        $postData = array(
            'Data' => array(
                'Website' => $this->_helper->getWinmanWebsite($this->_currentWebsite->getCode()),
                'TotalOrderValue' => $orderData['grand_total'],
                'TotalTaxValue' => $orderData['tax_amount'],
                'Coupon' => $orderData['coupon_code'],
                'CustomerOrderNumber' => $orderData['increment_id'],
                'CurrencyCode' => $orderData['order_currency_code'],
                'WebsiteUserName' => $orderData['customer_email']
            )
        );

        if (!$isGuest) {
            $postData['Data']['CustomerGuid'] = $guid;
        }

        $items = [];

        foreach ($orderItems as $key => $item) {
            $itemData = $item->getData();

            $items[$key] = array(
                'Sku' => $item->getProduct()->getSku(),
                'Quantity' => $itemData['qty_ordered'],
                'OrderLineValue' => $itemData['row_total_incl_tax'],
                'OrderLineTaxValue' => $itemData['tax_amount']
            );

            $options = isset($item->getProductOptions()['options']) ? $item->getProductOptions()['options'] : false;

            if ($options) {
                $items[$key]['UseConfigurator'] = true;
                $items[$key]['ConfiguredSku'] = $itemData['sku'];
                $items[$key]['Options'] = [];

                foreach ($options as $option) {
                    $optionValues = [];

                    if (strpos($option['option_value'], ',')) {
                        foreach (explode(',', $option['option_value']) as $v) {
                            $optionValues[] = $v;
                        }
                    } else {
                        $optionValues[] = $option['option_value'];
                    }

                    $optionData = $item->getProduct()->getOptionById($option['option_id'])->getValues();

                    foreach ($optionData as $value) {
                        if (in_array($value->getId(), $optionValues)) {
                            $items[$key]['Options'][] = (object)array(
                                'OptionId' => $option['label'],
                                'OptionItemId' => $value->getTitle(),
                                'OptionItemPrice' => ($value->getPrice()) ? $value->getPrice() : 0.00
                            );
                        }
                    }

                    unset($optionValues);
                }
            }
        }

        $postData['Data']['SalesOrderItems'] = $items;

        $shipping['FreightMethodId'] = explode(' - ', $orderData['shipping_description'])[1];
        $shipping['ShippingValue'] = $orderData['shipping_amount'] + $orderData['shipping_tax_amount'];
        $shipping['ShippingTaxValue'] = $orderData['shipping_tax_amount'];

        $postData['Data']['SalesOrderShipping'] = $shipping;

        $billing['PaymentType'] = $order->getPayment()->getMethodInstance()->getTitle();
        $billing['CardPaymentReceived'] = $orderData['total_paid'];

        $postData['Data']['SalesOrderBilling'] = $billing;

        $dataString = json_encode($postData);

        $response = $this->_helper->executeCurl($this->_currentWebsite->getCode(), $apiUrl, $dataString);

        if (isset($response->Response->Status) && $response->Response->Status === 'Success') {
            $message = __('Order successfully placed in WinMan. WinMan order ID') . ': ' . $response->Response->SalesOrderId;
            $order->setStatus('complete')->addStatusHistoryComment($message)->save();
            $order->setStatus('complete')->save();

            if (!$isGuest) {
                $this->updateCustomerGuid($orderData['customer_email'], $response->Response->CustomerGUID);
            }
        } else {
            $message = __('Order could not be placed in WinMan. The message from WinMan is') . ': ' . $response->Response->StatusMessage;
            $order->setStatus('holded')->addStatusHistoryComment($message)->save();
            $order->setStatus('holded')->save();

            $this->_notifier->addMajor(
                _('Order could not be placed in WinMan'),
                _('Order %1 could not be placed in WinMan. The message from WinMan is', $orderData['increment_id'])
                . ': ' . $response->Response->StatusMessage
            );
        }
    }

    /**
     * Return the specified address as an array suitable for the WinMan REST API.
     *
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $address
     * @param string $type
     * @return array
     */
    private function getAddress($address, $type = 'shipping')
    {
        $returnAddress = array(
            ucfirst($type) . 'Name' => $address->getFirstname() . ' ' . $address->getLastName(),
            ucfirst($type) . 'Address' => implode(chr(13) . chr(10), $address->getStreet()),
            ucfirst($type) . 'PostalCode' => $address->getPostcode(),
            ucfirst($type) . 'CountryCode' => $this->getCountryCode($address->getCountryId())
        );

        return $returnAddress;
    }

    /**
     * Fetch the specified customer's GUID.
     *
     * @param string $email
     * @return mixed
     */
    private function getCustomerGuid($email)
    {
        try {
            $customer = $this->_customerRepository->get($email, $this->_currentWebsite->getId());
        } catch (\Exception $e) {
            $customer = null;
        }
        $guid = (empty($customer->getCustomAttribute('guid'))) ? null : $customer->getCustomAttribute('guid')->getValue();

        return $guid;
    }

    /**
     * Update the specified customer's GUID with the one returned by the WinMan REST API.
     *
     * @param string $email
     * @param string $guid
     */
    private function updateCustomerGuid($email, $guid)
    {
        try {
            $customer = $this->_customerRepository->get($email, $this->_currentWebsite->getId());
            $customer->setCustomAttribute('guid', $guid);
            $this->_customerRepository->save($customer);
        } catch (\Exception $e) {
            $this->_logger->warning($e->getMessage());
        }
    }

    /**
     * Retrieve the 3-character country code for the specified country.
     *
     * @param integer $countryId
     * @return mixed
     */
    private function getCountryCode($countryId)
    {
        $country = $this->_countryFactory
            ->create()
            ->loadByCode($countryId);

        return $country->getData('iso3_code');
    }
}
