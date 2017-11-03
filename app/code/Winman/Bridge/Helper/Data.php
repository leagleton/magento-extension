<?php

namespace Winman\Bridge\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Store\Model\ScopeInterface;

/**
 * Class Data
 * @package Winman\Bridge\Helper
 */
class Data extends AbstractHelper
{
    /**
     * @param $config_path
     * @param $scope_code
     * @return mixed
     */
    public function getConfig($config_path, $scope_code)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            ScopeInterface::SCOPE_WEBSITES,
            $scope_code
        );
    }
}
