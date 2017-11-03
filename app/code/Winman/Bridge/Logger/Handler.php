<?php

namespace Winman\Bridge\Logger;

use \Monolog\Logger;
use \Magento\Framework\Logger\Handler\Base;

/**
 * Class Handler
 * @package Winman\Bridge\Logger
 */
class Handler extends Base
{
    protected $loggerType = Logger::INFO;
    protected $fileName = '/var/log/winman_bridge.log';
}
