<?php
/**
 * @author Lynn Eagleton <support@winman.com>
 */

namespace Winman\Bridge\Controller\Portal;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Winman\Bridge\Helper\Data;
use \Magento\Store\Model\StoreManagerInterface as StoreManager;
use \Magento\Customer\Model\SessionFactory;
use \Magento\Framework\Controller\ResultFactory;

/**
 * Class Quotes
 *
 * @package Winman\Bridge\Controller\Portal
 */
class Quotes extends Action
{
    /**
     * @var \Winman\Bridge\Helper\Data
     */
    protected $_helper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var mixed
     */
    protected $_websiteCode;

    /**
     * @var \Magento\Customer\Model\SessionFactory
     */
    protected $_customerSessionFactory;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;

    /**
     * Quotes constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Winman\Bridge\Helper\Data $helper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Model\SessionFactory $customerSessionFactory
     */
    public function __construct(
        Context $context,
        Data $helper,
        StoreManager $storeManager,
        SessionFactory $customerSessionFactory)
    {
        parent::__construct($context);

        $this->_helper = $helper;
        $this->_storeManager = $storeManager;
        $this->_customerSessionFactory = $customerSessionFactory;
        $this->_messageManager = $context->getMessageManager();

        $this->_websiteCode = $this->_storeManager->getStore()->getWebsite()->getCode();
    }


    /**
     * If the customer is not logged in, redirect to the login page.
     * If the customer does not have a WinMan Customer GUID, redirect to the
     * regular Magento account dashboard.
     * If the customer is logged in and has a WinMan Customer GUID, display the quotes page.
     * If the action, reference and quoteid parameters are specified, attempt to convert the
     * specified quote to an order and redirect to the quotes page.
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if (!$this->_customerSessionFactory->create()->isLoggedIn()) {
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl('/customer/account/login');

            return $resultRedirect;
        }

        if (!$this->_customerSessionFactory->create()->getCustomer()->getGuid()) {
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl('/customer/account');

            return $resultRedirect;
        }

        $action = $this->getRequest()->getParam('action');
        $reference = $this->getRequest()->getParam('reference');
        $id = $this->getRequest()->getParam('quoteid');

        if (isset($action) && $action == 'convertquote' && isset($id) && isset($reference)) {
            $postData = array(
                'Data' => array(
                    'Website' => $this->_helper->getWinmanWebsite($this->_websiteCode),
                    'CustomerGuid' => $this->_customerSessionFactory->create()->getCustomer()->getGuid(),
                    'CustomerOrderNumber' => $reference,
                    'QuoteId' => $id
                )
            );

            $this->convertQuote($postData);

            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl('/customerportal/portal/quotes');

            return $resultRedirect;
        } else {
            $this->_view->loadLayout();
            $this->_view->renderLayout();
        }
    }


    /**
     * Attempt to convert the specified quote to an order by making the
     * appropriate request to the WinMan REST API.
     * Add the returned status message to the page.
     *
     * @param array $data
     */
    private function convertQuote($data)
    {
        $apiUrl = $this->_helper->getApiBaseUrl($this->_websiteCode)
            . '/convertquotes';

        $response = $this->_helper->executeCurl($this->_websiteCode, $apiUrl, json_encode($data), true);

        if ($response === '' || (isset($response->Response->Status) && $response->Response->Status === 'Success')) {
            $message = __('Quote %1 has been converted to an order.', $data['Data']['QuoteId']);
            $this->_messageManager->addSuccessMessage($message);
        } else {
            $message = __('There was a problem making your request.');
            $this->_messageManager->addErrorMessage($message);
        }
    }
}
