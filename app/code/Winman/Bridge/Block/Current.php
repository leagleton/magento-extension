<?php
/**
 * @author Lynn Eagleton <support@winman.com>
 */

namespace Winman\Bridge\Block;

use \Magento\Framework\View\Element\Template;
use \Magento\Customer\Model\Session;
use \Magento\Framework\App\DefaultPathInterface;
use \Magento\Framework\Phrase;

/**
 * Class Current
 *
 * @package Winman\Bridge\Block
 */
class Current extends \Magento\Framework\View\Element\Html\Link\Current
{
    /**
     * @var \Magento\Framework\App\DefaultPathInterface
     */
    protected $_defaultPath;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * Current constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\App\DefaultPathInterface $defaultPath
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        Template\Context $context,
        DefaultPathInterface $defaultPath,
        Session $customerSession)
    {
        parent::__construct($context, $defaultPath);

        $this->_customerSession = $customerSession;
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function toHtml()
    {
        if (!$this->isWinmanCustomer()) {
            return null;
        }

        $highlight = '';

        if ($this->isCurrent()) {
            $highlight = ' current';
        }

        $html = '<li class="nav item' . $highlight . '"><a href="' . $this->escapeHtml($this->getHref()) . '"';
        $html .= $this->getTitle()
            ? ' title="' . $this->escapeHtml((string)new Phrase($this->getTitle())) . '"'
            : '';
        $html .= '>' . $this->escapeHtml((string)new Phrase($this->getLabel())) . '</a></li>';

        return $html;
    }

    /**
     * Determine whether the customer is logged in.
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
