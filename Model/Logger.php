<?php

namespace Loomis\Shipping\Model;

use Magento\Framework\Logger\Monolog;

class Logger extends Monolog
{
    /**
     * Logger constructor.
     * @param $name
     * @param LoggerHandler $handler
     * @param array $handlers
     * @param array $processors
     */
    public function __construct(
        $name,
        LoggerHandler $handler,
        array $handlers = [],
        array $processors = []
    )
    {
        $handlers[] = $handler;
        parent::__construct($name, $handlers, $processors);
    }
}
