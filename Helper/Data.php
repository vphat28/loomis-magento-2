<?php

namespace Loomis\Shipping\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Data
{
    const LOOMIS_URI_RATING_PROD = "https://webservice.loomis-express.com/LShip/services/USSRatingService?wsdl";

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

    public function getLoomisPassword($store)
    {
        return $this->scopeConfig->getValue('carriers/loomisrate/password', ScopeInterface::SCOPE_STORE, $store);
    }

    public function getLoomisUsername($store)
    {
        return $this->scopeConfig->getValue('carriers/loomisrate/username', ScopeInterface::SCOPE_STORE, $store);
    }

    public function getLoomisAccountNumber($store)
    {
        return $this->scopeConfig->getValue('carriers/loomisrate/account_number', ScopeInterface::SCOPE_STORE, $store);
    }

    public function getRatingEndpoint()
    {
        return self::LOOMIS_URI_RATING_PROD;
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
