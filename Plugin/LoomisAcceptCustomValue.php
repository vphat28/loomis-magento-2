<?php

namespace Loomis\Shipping\Plugin;

use Loomis\Shipping\Model\Carrier\Loomis;
use Magento\Shipping\Block\Adminhtml\Order\Packaging;

class LoomisAcceptCustomValue
{
    /**
     * @param Packaging $subject
     * @param $value
     * @return bool
     */
    public function afterDisplayCustomsValue($subject, $value)
    {
        $shipment = $subject->getShipment();
        $order = $shipment->getOrder();
        $carrierCode = $order->getShippingMethod(true)->getCarrierCode();

        if ($carrierCode === 'loomisrate') {
            return true;
        } else {
            return $value;
        }
    }

}
