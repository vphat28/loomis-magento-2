<?php

namespace Loomis\Shipping\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Data
{
    const LOOMIS_URI_RATING_SANDBOX = "https://sandbox.loomis-express.com/axis2/services/USSRatingService?wsdl";
    const LOOMIS_URI_SHIPPING_SANDBOX = "https://sandbox.loomis-express.com/axis2/services/USSBusinessService?wsdl";
    const LOOMIS_URI_ADDON_SANDBOX = "https://sandbox.loomis-express.com/axis2/services/USSAddonsService?wsdl";
    const LOOMIS_URI_RATING_PROD = "https://webservice.loomis-express.com/LShip/services/USSRatingService?wsdl";
    const LOOMIS_URI_SHIPPING_PROD = "https://webservice.loomis-express.com/LShip/services/USSBusinessService?wsdl";
    const LOOMIS_URI_ADDON_PROD = "https://webservice.loomis-express.com/LShip/services/USSAddonsService?wsdl";

    /** @var \Loomis\Shipping\Model\Logger */
    private $logger;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var \Magento\Directory\Model\RegionFactory */
    private $regionFactory;

    public function __construct(
        \Loomis\Shipping\Model\Logger $logger,
        ScopeConfigInterface $scopeConfig,
        \Magento\Directory\Model\RegionFactory $regionFactory
    )
    {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->regionFactory = $regionFactory;
    }

    /**
     * @return \Loomis\Shipping\Model\Logger
     */
    public function logger()
    {
        return $this->logger;
    }

    public function getWeightUnit($store = null)
    {
        return $this->scopeConfig->getValue('general/locale/weight_unit', ScopeInterface::SCOPE_STORE, $store);
    }

    public function getLoomisPassword($store = null)
    {
        return $this->scopeConfig->getValue('carriers/loomisrate/password', ScopeInterface::SCOPE_STORE, $store);
    }

    public function getLoomisUsername($store = null)
    {
        return $this->scopeConfig->getValue('carriers/loomisrate/username', ScopeInterface::SCOPE_STORE, $store);
    }

    public function getLoomisAccountNumber($store = null)
    {
        return $this->scopeConfig->getValue('carriers/loomisrate/account_number', ScopeInterface::SCOPE_STORE, $store);
    }

    public function getRatingEndpoint($store = null)
    {
        $sandbox = $this->scopeConfig->isSetFlag('carriers/loomisrate/sandbox', ScopeInterface::SCOPE_STORE, $store);

        if ($sandbox) {
            return self::LOOMIS_URI_RATING_SANDBOX;
        } else {
            return self::LOOMIS_URI_RATING_PROD;
        }
    }

    public function getShippingEndpoint($store = null)
    {
        $sandbox = $this->scopeConfig->isSetFlag('carriers/loomisrate/sandbox', ScopeInterface::SCOPE_STORE, $store);

        if ($sandbox) {
            return self::LOOMIS_URI_SHIPPING_SANDBOX;
        } else {
            return self::LOOMIS_URI_SHIPPING_PROD;
        }
    }

    public function getAddonEndpoint($store = null)
    {
        $sandbox = $this->scopeConfig->isSetFlag('carriers/loomisrate/sandbox', ScopeInterface::SCOPE_STORE, $store);

        if ($sandbox) {
            return self::LOOMIS_URI_ADDON_SANDBOX;
        } else {
            return self::LOOMIS_URI_ADDON_PROD;
        }
    }

    public function getServiceType()
    {
        return array(
            "DD" => "Loomis Ground",
            "DE" => "Loomis Express 18:00",
            "DN" => "Loomis Express 12:00",
            "D9" => "Loomis Express 9:00"
        );
    }

    /**
     * @param $store
     * @return \Magento\Directory\Model\Region
     */
    public function getProvince($store)
    {
        $regionId = $this->scopeConfig->getValue('shipping/origin/region_id', ScopeInterface::SCOPE_STORE, $store);
        $this->logger->debug('Getting province ' . $regionId);
        /** @var \Magento\Directory\Model\Region $province */
        $province = $this->regionFactory->create();
        $province->load($regionId);

        return $province;
    }

    /**
     * @param $store
     * @return string
     */
    public function getOriginCity($store)
    {
        return $this->scopeConfig->getValue('shipping/origin/city', ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @param $store
     * @return string
     */
    public function getOriginPostcode($store)
    {
        return $this->scopeConfig->getValue('shipping/origin/postcode', ScopeInterface::SCOPE_STORE, $store);
    }
}
