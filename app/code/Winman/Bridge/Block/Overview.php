<?php

namespace Winman\Bridge\Block;

use \Magento\Framework\View\Element\Template;

/**
 * Class Overview
 * @package Winman\Bridge\Block
 */
class Overview extends Template
{
    /**
     * Overview constructor.
     * @param Template\Context $context
     */
    public function __construct(Template\Context $context)
    {
        parent::__construct($context);
    }
}
