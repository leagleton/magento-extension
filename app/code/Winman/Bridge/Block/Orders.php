<?php

namespace Winman\Bridge\Block;

use \Magento\Framework\View\Element\Template;

/**
 * Class Orders
 * @package Winman\Bridge\Block
 */
class Orders extends Template
{
    /**
     * Orders constructor.
     * @param Template\Context $context
     */
    public function __construct(Template\Context $context)
    {
        parent::__construct($context);
    }
}
