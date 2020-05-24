<?php

namespace Loomis\Shipping\Model;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class LoggerHandler extends Base
{
    protected $fileName = '/var/log/loomis.log';
    protected $loggerType = Logger::DEBUG;
}
