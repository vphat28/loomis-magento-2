<?php

namespace Loomis\Shipping\Helper;

class Data
{
    /** @var \Loomis\Shipping\Model\Logger */
    private $logger;

    public function __construct(
        \Loomis\Shipping\Model\Logger $logger
    )
    {
        $this->logger = $logger;
    }

    /**
     * @return \Loomis\Shipping\Model\Logger
     */
    public function logger()
    {
        return $this->logger;
    }
}
