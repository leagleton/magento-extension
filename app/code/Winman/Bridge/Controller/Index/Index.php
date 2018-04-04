<?php
/**
 * @author Lynn Eagleton <support@winman.com>
 */

namespace Winman\Bridge\Controller\Index;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Winman\Bridge\Helper\Data;
use \Magento\Store\Model\StoreManager;

/**
 * Class Index
 *
 * @package Winman\Bridge\Controller\Index
 */
class Index extends Action
{
    /**
     * @var \Winman\Bridge\Helper\Data
     */
    protected $_helper;

    /**
     * @var \Magento\Store\Model\StoreManager
     */
    protected $_storeManager;

    /**
     * @var string
     */
    private $_websiteCode;

    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Winman\Bridge\Helper\Data $helper
     * @param \Magento\Store\Model\StoreManager $storeManager
     */
    public function __construct(
        Context $context,
        Data $helper,
        StoreManager $storeManager)
    {
        parent::__construct($context);

        $this->_helper = $helper;
        $this->_storeManager = $storeManager;
        $this->_websiteCode = $this->_storeManager->getStore()->getWebsite()->getCode();
    }


    /**
     * If the form has been submitted, send the data to the WinMan REST API.
     * Otherwise, render the Request Account form.
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if ($this->_helper->getEnabled($this->_websiteCode)) {
            $post = $this->getRequest()->getPost();

            if ($post['firstname']) {
                $this->postRequest($post);

                $resultRedirect = $this->resultFactory->create('redirect');
                $resultRedirect->setUrl('/requestaccount');

                return $resultRedirect;
            }
        }

        $this->_view->loadLayout();
        $this->_view->renderLayout();
    }

    /**
     * Send the form data to the WinMan REST API.
     *
     * @param mixed $data
     */
    public function postRequest($data)
    {
        $apiUrl = $this->_helper->getApiBaseUrl($this->_websiteCode) . '/customers';

        $communication = (isset($data['allow_communication'])) ? true : false;
        $postData = array(
            'Data' => array(
                'Website' => $this->_helper->getWinmanWebsite($this->_websiteCode),
                'FirstName' => $data['firstname'],
                'LastName' => $data['lastname'],
                'WorkPhoneNumber' => $data['phone_number'],
                'WebsiteUserName' => $data['email_address'],
                'JobTitle' => $data['job_title'],
                'AllowCommunication' => $communication,
                'Address' => $data['address'],
                'City' => $data['city'],
                'Region' => $data['region'],
                'PostalCode' => $data['postal_code'],
                'CountryCode' => $data['country']
            )
        );
        $dataString = json_encode($postData);

        $response = $this->_helper->executeCurl($this->_websiteCode, $apiUrl, $dataString);

        if (isset($response->Response->Status) && $response->Response->Status === 'Success') {
            $message = __('Your request has been submitted');
            $this->messageManager->addSuccessMessage($message);
        } else {
            $message = __('There was a problem making your request');
            $this->messageManager->addErrorMessage($message);
        }
    }
}
