<?php

namespace Winman\Bridge\Block;

use \Magento\Framework\View\Element\Template;
use \Winman\Bridge\Helper\Data;

/**
 * Class Invoices
 *
 * @package Winman\Bridge\Block
 */
class Invoices extends Template
{
    /**
     * @var \Winman\Bridge\Helper\Data
     */
    protected $_helper;

    /**
     * Invoices constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Winman\Bridge\Helper\Data $helper
     */
    public function __construct(
        Template\Context $context,
        Data $helper)
    {
        parent::__construct($context);
        $this->_helper = $helper;
    }
}
