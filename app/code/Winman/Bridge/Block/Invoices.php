<?php

namespace Winman\Bridge\Block;

use \Magento\Framework\View\Element\Template;

/**
 * Class Invoices
 * @package Winman\Bridge\Block
 */
class Invoices extends Template
{
    /**
     * Invoices constructor.
     * @param Template\Context $context
     */
    public function __construct(Template\Context $context)
    {
        parent::__construct($context);
    }
}
