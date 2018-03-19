<?php

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

    protected $_logger;
    protected $_helper;
    protected $_storeManager;
    protected $_customerRepository;
    protected $_addressRepository;
    protected $_countryFactory;

    protected $_orderRepository;

    protected $_notifier;

    /**
     * Order constructor.
     * @param Logger $logger
     * @param Data $helper
     * @param OrderRepository $orderRepository
     * @param CustomerRepository $customerRepository
     * @param AddressRepository $addressRepository
     * @param CountryFactory $countryFactory
     * @param StoreManager $storeManager
     * @param Notifier $notifier
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
        $websiteCode = $this->_currentWebsite->getCode();

        $this->_ACCESS_TOKEN = $this->_helper->getconfig('winman_bridge/general/access_token', $websiteCode);
        $this->_API_BASEURL = $this->_helper->getconfig('winman_bridge/general/api_baseurl', $websiteCode);
        $this->_WINMAN_WEBSITE = $this->_helper->getconfig('winman_bridge/general/winman_website', $websiteCode);
        $this->_ENABLED = $this->_helper->getconfig('winman_bridge/general/enable', $websiteCode);
        $this->_ENABLE_ORDERS = $this->_helper->getconfig('winman_bridge/sales_orders/enable_salesorders', $websiteCode);

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

            try {
                $order = $this->_orderRepository->get($order_id);
                $this->postRequest($order);
            } catch (\Exception $e) {
                $this->_logger->alert($e->getMessage());
            }
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
        $isGuest = $orderData['customer_is_guest'];
        $guid = ($isGuest) ? null : $this->getCustomerGuid($orderData['customer_email']);
        $shipping = $this->getAddress($order->getShippingAddress());
        $billing = $this->getAddress($order->getBillingAddress(), 'billing');

        $postData = array(
            'Data' => array(
                'Website' => $this->_WINMAN_WEBSITE,
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

        $response = $this->executeCurl($apiUrl, $dataString);

        if (isset($response->Response->Status) && $response->Response->Status === 'Success') {
            $message = __('Order successfully placed in WinMan. WinMan order ID: ') . $response->Response->SalesOrderId;
            $order->setStatus('complete')->addStatusHistoryComment($message)->save();
            $order->setStatus('complete')->save();

            if (!$isGuest) {
                $this->updateCustomerGuid($orderData['customer_email'], $response->Response->CustomerGUID);
            }
        } else {
            $message = __('Order could not be placed in WinMan. The message from WinMan is: ') . $response->Response->StatusMessage;
            $order->setStatus('holded')->addStatusHistoryComment($message)->save();
            $order->setStatus('holded')->save();

            $this->_notifier->addMajor(
                'Order could not be placed in WinMan',
                'Order ' . $orderData['increment_id'] .
                ' could not be placed in WinMan. The message from WinMan is: '
                . $response->Response->StatusMessage
            );
        }
    }

    /**
     * @param $address
     * @param $type
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
     * @param $email
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
     * @param $email
     * @param $guid
     */
    private function updateCustomerGuid($email, $guid)
    {
        try {
            $customer = $this->_customerRepository->get($email, $this->_currentWebsite->getId());
            $customer->setCustomAttribute('guid', $guid);
            $this->_customerRepository->save($customer);
        } catch (\Exception $e) {
            $this->_logger->alert($e->getMessage());
        }
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
        $response = curl_exec($curl);

        if (!$response) {
            return '';
        }

        $decoded = json_decode($response);
        curl_close($curl);

        return $decoded;
    }
}
