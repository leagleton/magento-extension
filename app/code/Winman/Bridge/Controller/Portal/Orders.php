<?php
/**
 * @author Lynn Eagleton <support@winman.com>
 */

namespace Winman\Bridge\Controller\Portal;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Magento\Customer\Model\SessionFactory;

/**
 * Class Orders
 *
 * @package Winman\Bridge\Controller\Portal
 */
class Orders extends Action
{
    /**
     * @var \Magento\Customer\Model\SessionFactory
     */
    protected $_customerSessionFactory;

    /**
     * Orders constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\SessionFactory $customerSessionFactory
     */
    public function __construct(
        Context $context,
        SessionFactory $customerSessionFactory
    )
    {
        parent::__construct($context);

        $this->_customerSessionFactory = $customerSessionFactory;
    }

    /**
     * If the customer is not logged in, redirect to the login page.
     * If the customer does not have a WinMan Customer GUID, redirect to the
     * regular Magento account dashboard.
     * If the customer is logged in and has a WinMan Customer GUID, display the orders page.
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

        $this->_view->loadLayout();
        $this->_view->renderLayout();
    }
}
