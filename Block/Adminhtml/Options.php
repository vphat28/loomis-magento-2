<?php

namespace Loomis\Shipping\Block\Adminhtml;

use Magento\Framework\View\Element\Template;

class Options extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;


    public function __construct(
        Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
    }
    /**
     * @return bool
     */
    public function checkIsLoomisShipment()
    {
        $shipment = $this->registry->registry('current_shipment');

        if(empty($shipment)) {
            return false;
        }

        $shippingMethod = $shipment->getOrder()->getShippingMethod(true);
        if ($shippingMethod->getCarrierCode() != 'loomisrate') {
            return false;
        }

        return true;
    }

    public function getAvailableOptions()
    {
        $options = [];
        $option = new \stdClass();
        $option->ID = 'Insurance';
        $option->ValueType = 'Decimal';
        $option->Description = 'Insurance description';
        $options[] = $option;

        return $options;
    }
}
