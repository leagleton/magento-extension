<?php

namespace Winman\Bridge\Controller\Index;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Winman\Bridge\Helper\Data;
use \Magento\Store\Model\StoreManager;
use \Magento\Framework\Controller\ResultFactory;

/**
 * Class Index
 * @package Winman\Bridge\Controller\Index
 */
class Index extends Action
{
    private $_ACCESS_TOKEN;
    private $_API_BASEURL;
    private $_WINMAN_WEBSITE;
    private $_ENABLED;
    private $_CURL_HEADERS;

    private $_websiteCode;

    protected $_messageManager;
    protected $_helper;
    protected $_storeManager;

    /**
     * Index constructor.
     * @param Context $context
     * @param Data $helper
     * @param StoreManager $storeManager
     */
    public function __construct(
        Context $context,
        Data $helper,
        StoreManager $storeManager)
    {
        parent::__construct($context);
        $this->_messageManager = $context->getMessageManager();
        $this->_helper = $helper;
        $this->_storeManager = $storeManager;

        $this->_websiteCode = $this->_storeManager->getStore()->getWebsite()->getCode();

        $this->_ACCESS_TOKEN = $this->_helper->getconfig('winman_bridge/general/access_token', $this->_websiteCode);
        $this->_API_BASEURL = $this->_helper->getconfig('winman_bridge/general/api_baseurl', $this->_websiteCode);
        $this->_WINMAN_WEBSITE = $this->_helper->getconfig('winman_bridge/general/winman_website', $this->_websiteCode);
        $this->_ENABLED = $this->_helper->getconfig('winman_bridge/general/enable', $this->_websiteCode);

        $headers = array();
        $headers[] = 'accept: application/json';
        $headers[] = 'content-type: application/json';
        $headers[] = 'authorization: Bearer ' . $this->_ACCESS_TOKEN;

        $this->_CURL_HEADERS = $headers;
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if ($this->_ENABLED) {
            $post = $this->getRequest()->getPost();

            if ($post['firstname']) {
                $this->postRequest($post);

                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                $resultRedirect->setUrl('/requestaccount');

                return $resultRedirect;
            }
        }

        $this->_view->loadLayout();
        $this->_view->renderLayout();
    }

    /**
     * @param $data
     */
    public function postRequest($data)
    {
        $apiUrl = $this->_API_BASEURL . '/customers';

        $communication = (isset($data['allow_communication'])) ? true : false;
        $postData = array(
            'Data' => array(
                'Website' => $this->_WINMAN_WEBSITE,
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

        $response = $this->executeCurl($apiUrl, $dataString);

        if ($response->Response->Status === 'Error' || $response = '') {
            switch ($response->Response->StatusMessage) {
                case 'ERROR: Specified User Name already exists. Please check your input data.':
                    $message = __('Account already exists with this email address.');
                    break;
                default:
                    $message = __('There was a problem making your request.');
            }

            $this->_messageManager->addErrorMessage($message);
        } else {
            $message = __('Your request has been submitted.');
            $this->messageManager->addSuccessMessage($message);
        }
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
