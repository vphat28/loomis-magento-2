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

    /** @var \Magento\Directory\Model\ResourceModel\Region\CollectionFactory */
    private $provinceCollectionFactory;

    public function __construct(
        \Loomis\Shipping\Model\Logger $logger,
        ScopeConfigInterface $scopeConfig,
        \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $provinceCollectionFactory
    )
    {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->provinceCollectionFactory = $provinceCollectionFactory;
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
     * @param $provinceID
     * @return \Magento\Directory\Model\Region
     */
    public function getProvinceFromID($provinceID)
    {
        /** @var \Magento\Directory\Model\ResourceModel\Region\Collection $collection */
        $collection = $this->provinceCollectionFactory->create();
        /** @var \Magento\Directory\Model\Region $province */
        $province = $collection->addFieldToFilter('region_id', ['eq' => $provinceID])
            ->getFirstItem();

        return $province;
    }
}
