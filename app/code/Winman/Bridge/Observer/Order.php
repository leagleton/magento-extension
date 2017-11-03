<?php

namespace Winman\Bridge\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\ObjectManagerInterface;
use \Magento\Framework\Event\Observer;
use \Winman\Bridge\Logger\Logger;
use \Winman\Bridge\Helper\Data;
use \Magento\Store\Model\StoreManager;
use \Magento\Sales\Model\OrderRepository;
use \Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use \Magento\Customer\Api\AddressRepositoryInterface as AddressRepository;
use \Magento\Directory\Model\CountryFactory;

/**
 * Class Order
 * @package Winman\Bridge\Observer
 */
class Order implements ObserverInterface
{
    private $_ACCESS_TOKEN;
    private $_API_BASEURL;
    private $_WINMAN_WEBSITE;
    private $_ENABLED;
    private $_ENABLE_ORDERS;
    private $_CURL_HEADERS;

    private $_currentWebsite;

    protected $_objectManager;
    protected $_logger;
    protected $_helper;
    protected $_storeManager;
    protected $_customerRepository;
    protected $_addressRepository;
    protected $_countryFactory;

    protected $_orderRepository;

    /**
     * Order constructor.
     * @param ObjectManagerInterface $objectManager
     * @param Logger $logger
     * @param Data $helper
     * @param OrderRepository $orderRepository
     * @param CustomerRepository $customerRepository
     * @param AddressRepository $addressRepository
     * @param CountryFactory $countryFactory
     * @param StoreManager $storeManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Logger $logger,
        Data $helper,
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        AddressRepository $addressRepository,
        CountryFactory $countryFactory,
        StoreManager $storeManager)
    {
        $this->_objectManager = $objectManager;
        $this->_logger = $logger;
        $this->_helper = $helper;
        $this->_orderRepository = $orderRepository;
        $this->_customerRepository = $customerRepository;
        $this->_addressRepository = $addressRepository;
        $this->_countryFactory = $countryFactory;

        $this->_storeManager = $storeManager;

        $this->_currentWebsite = $this->_storeManager->getStore()->getWebsite();

        $this->_ACCESS_TOKEN = $this->_helper->getconfig('winman_bridge/general/access_token', $this->_currentWebsite->getCode());
        $this->_API_BASEURL = $this->_helper->getconfig('winman_bridge/general/api_baseurl', $this->_currentWebsite->getCode());
        $this->_WINMAN_WEBSITE = $this->_helper->getconfig('winman_bridge/general/winman_website', $this->_currentWebsite->getCode());
        $this->_ENABLED = $this->_helper->getconfig('winman_bridge/general/enable', $this->_currentWebsite->getCode());
        $this->_ENABLE_ORDERS = $this->_helper->getconfig('winman_bridge/sales_orders/enable_salesorders', $this->_currentWebsite->getCode());

        $headers = array();
        $headers[] = 'accept: application/json';
        $headers[] = 'content-type: application/json';
        $headers[] = 'authorization: Bearer ' . $this->_ACCESS_TOKEN;

        $this->_CURL_HEADERS = $headers;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if ($this->_ENABLED && $this->_ENABLE_ORDERS) {
            $order_ids = $observer->getEvent()->getOrderIds();
            $order_id = $order_ids[0];

            $order = $this->_orderRepository->get($order_id);

            $this->postRequest($order);
        }
    }

    /**
     * @param $order
     */
    public function postRequest($order)
    {
        $apiUrl = $this->_API_BASEURL . '/salesorders';

        $orderItems = $order->getAllItems();
        $orderData = $order->getData();
        $shipping = $this->getShippingAddress($order->getShippingAddress());

        $postData = array(
            'Data' => array(
                'Website' => $this->_WINMAN_WEBSITE,
                'CustomerGuid' => $this->getCustomerGuid($orderData['customer_email']),
                'TotalOrderValue' => $orderData['grand_total'],
                'CustomerOrderNumber' => $orderData['increment_id'],
                'CustomerContact' => $orderData['customer_firstname'] . ' ' . $orderData['customer_lastname'],
                'CurrencyCode' => $orderData['order_currency_code'],
                'WebsiteUserName' => $orderData['customer_email']
            )
        );

        $items = [];

        foreach ($orderItems as $item) {
            $itemData = $item->getData();

            $items[] = array(
                'Sku' => $itemData['sku'],
                'Quantity' => $itemData['qty_ordered'],
                'Price' => $itemData['price']
            );
        }

        $postData['Data']['SalesOrderItems'] = $items;

        $shipping['FreightMethodId'] = explode(' - ', $orderData['shipping_description'])[1];
        $shipping['Price'] = $orderData['shipping_amount'];

        $postData['Data']['SalesOrderShipping'] = $shipping;

        $billing = array(
            'PaymentType' => $order->getPayment()->getMethodInstance()->getTitle(),
            'CardPaymentReceived' => $orderData['total_paid']
        );

        $postData['Data']['SalesOrderBilling'] = $billing;

        $dataString = json_encode($postData);

        $response = $this->executeCurl($apiUrl, $dataString);

        if ($response->Response->Status === 'Success') {
            $message = __('Order successfully placed in WinMan. WinMan order ID: ' . $response->Response->SalesOrderId);
            $order->setStatus('complete')->addStatusHistoryComment($message)->save();
            $order->setStatus('complete')->save();
        } else {
            $message = __('Order could not be placed in WinMan. Please check logs for more information.');
            $order->setStatus('holded')->addStatusHistoryComment($message)->save();
            $order->setStatus('holded')->save();

            $this->_logger->info($response->Response->StatusMessage);
        }
    }

    /**
     * @param $address
     * @return array
     */
    private function getShippingAddress($address)
    {
        $shippingAddress = array(
            'ShippingName' => $address->getFirstname() . ' ' . $address->getLastName(),
            'ShippingAddress' => $address->getStreet()[0],
            'ShippingPostalCode' => $address->getPostcode(),
            'ShippingCountryCode' => $this->getCountryCode($address->getCountryId())
        );

        return $shippingAddress;
    }

    /**
     * @param $email
     * @return mixed
     */
    private function getCustomerGuid($email)
    {
        $customer = $this->_customerRepository->get($email, $this->_currentWebsite->getId());

        return $customer->getCustomAttribute('guid')->getValue();
    }

    /**
     * @param $countryId
     * @return mixed
     */
    private function getCountryCode($countryId)
    {
        $country = $this->_countryFactory
            ->create()
            ->loadByCode($countryId);

        return $country->getData('iso3_code');
    }

    /**
     * @param $apiUrl
     * @param $data
     * @return mixed|string
     */
    private function executeCurl($apiUrl, $data)
    {
        $curl = curl_init($apiUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->_CURL_HEADERS);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // TODO: get rid of this!
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // TODO: get rid of this!
        $response = curl_exec($curl);

        if (!$response) {
            return '';
        }

        $decoded = json_decode($response);
        curl_close($curl);

        return $decoded;
    }
}