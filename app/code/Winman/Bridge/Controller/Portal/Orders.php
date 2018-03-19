<?php

namespace Winman\Bridge\Controller\Portal;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;

/**
 * Class Orders
 * @package Winman\Bridge\Controller\Portal
 */
class Orders extends Action
{
    /**
     * Orders constructor.
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $this->_view->loadLayout();
        $this->_view->renderLayout();
    }
}
