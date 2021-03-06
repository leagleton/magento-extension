<?php
/**
 * @author Lynn Eagleton <support@winman.com>
 */

namespace Winman\Bridge\Controller\Portal;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Winman\Bridge\Helper\Data;
use \Magento\Store\Model\StoreManager;
use \Magento\Customer\Model\SessionFactory;

/**
 * Class Overview
 *
 * @package Winman\Bridge\Controller\Portal
 */
class Overview extends Action
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
     * Overview constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Winman\Bridge\Helper\Data $helper
     * @param \Magento\Store\Model\StoreManager $storeManager
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

        $this->_websiteCode = $this->_storeManager->getStore()->getWebsite()->getCode();
    }

    /**
     * If the customer is not logged in, redirect to the login page.
     * If the customer does not have a WinMan Customer GUID, redirect to the
     * regular Magento account dashboard.
     * If the customer is logged in and has a WinMan Customer GUID, display the overview page.
     * If the pdf parameter is specified, the appropriate pdf will be returned instead of a page
     * being rendered.
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if (!$this->_customerSessionFactory->create()->isLoggedIn()) {
            $resultRedirect = $this->resultFactory->create('redirect');
            $resultRedirect->setUrl('/customer/account/login');

            return $resultRedirect;
        }

        try {
            if (!$this->_customerSessionFactory->create()->getCustomer()->getGuid()) {
                $resultRedirect = $this->resultFactory->create('redirect');
                $resultRedirect->setUrl('/customer/account');

                return $resultRedirect;
            }
        } catch (\Exception $e) {
            $resultRedirect = $this->resultFactory->create('redirect');
            $resultRedirect->setUrl('/customer/account');

            return $resultRedirect;
        }

        $pdfType = $this->getRequest()->getParam('pdf');

        $salesOrderId = $this->getRequest()->getParam('salesorderid');
        $salesInvoiceId = $this->getRequest()->getParam('salesinvoiceid');
        $quoteId = $this->getRequest()->getParam('quoteid');

        if (isset($pdfType)) {
            if (isset($salesOrderId)) {
                $fileName = __('Acknowledgement') . ' ' . $salesOrderId;
                $content = base64_decode($this->getPdf($pdfType, $salesOrderId));
            } else if (isset($salesInvoiceId)) {
                $fileName = __('Sales Invoice') . ' ' . $salesInvoiceId;
                $content = base64_decode($this->getPdf($pdfType, $salesInvoiceId));
            } else if (isset($quoteId)) {
                $fileName = __('Quotation') . ' ' . $quoteId;
                $content = base64_decode($this->getPdf($pdfType, $quoteId));
            } else {
                $fileName = __('Statement') . ' ' . date('M-d-Y');
                $content = base64_decode($this->getPdf($pdfType));
            }

            $fileName .= '.pdf';

            $file = fopen($fileName, 'wb');
            fwrite($file, $content);
            fclose($file);

            if (file_exists($fileName)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($fileName));
                readfile($fileName);
            }

            unlink($fileName);
        } else {
            $this->_view->loadLayout();
            $this->_view->renderLayout();
        }
    }

    /**
     * Fetch the appropriate PDF file data from the WinMan REST API.
     *
     * @param string $type
     * @param string $id
     * @return string|mixed
     */
    private function getPdf($type, $id = null)
    {
        $pdfId = '';

        if (!is_null($id)) {
            switch ($type) {
                case 'salesorder':
                    $pdfId = '&salesorderid=' . $id;
                    break;
                case 'salesinvoice':
                    $pdfId = '&salesinvoiceid=' . $id;
                    break;
                case 'quote':
                    $pdfId = '&quoteid=' . $id;
                    break;
                default:
                    $pdfId = '';
            }
        }

        try {
            $apiUrl = $this->_helper->getApiBaseUrl($this->_websiteCode)
                . '/pdfs?website='
                . urlencode($this->_helper->getWinmanWebsite($this->_websiteCode))
                . '&customerguid=' . $this->_customerSessionFactory->create()->getCustomer()->getGuid()
                . '&returntype=' . $type . $pdfId;
        } catch (\Exception $e) {
            return '';
        }

        $response = $this->_helper->executeCurl($this->_websiteCode, $apiUrl);

        if ($response && isset($response->Pdf->Data)) {
            return $response->Pdf->Data;
        }

        return '';
    }
}
