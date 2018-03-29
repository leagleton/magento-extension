<?php
/**
 * @author Lynn Eagleton <support@winman.com>
 */

namespace Winman\Bridge\Block;

use \Magento\Framework\View\Element\Template;
use \Magento\Customer\Model\Session;

/**
 * Class NavigationDelimiter
 *
 * @package Winman\Bridge\Block
 */
class NavigationDelimiter extends Template
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * Invoices constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        Template\Context $context,
        Session $customerSession)
    {
        parent::__construct($context);

        $this->_customerSession = $customerSession;
    }

    /**
     * Determine whether the customer is logged in and has a WinMan Customer GUID.
     *
     * @return boolean
     */
    public function isWinmanCustomer()
    {
        if (!$this->_customerSession->isLoggedIn()) {
            return false;
        }

        try {
            if (!$this->_customerSession->getCustomer()->getGuid()) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
