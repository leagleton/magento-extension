<?php

namespace Winman\Bridge\Block;

use \Magento\Framework\View\Element\Template;

/**
 * Class Quotes
 * @package Winman\Bridge\Block
 */
class Quotes extends Template
{
    /**
     * Quotes constructor.
     * @param Template\Context $context
     */
    public function __construct(Template\Context $context)
    {
        parent::__construct($context);
    }
}
