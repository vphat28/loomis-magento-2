<?php

namespace Loomis\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;

class Loomis extends AbstractCarrier implements CarrierInterface
{
    const DATE_FORMAT = "Y-m-d H:i:s O";
    /**
     * @var string
     */
    protected $_code = 'loomisrate';

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $_rateMethodFactory;

    /** @var \Loomis\Shipping\Helper\Data */
    private $helper;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Loomis\Shipping\Helper\Data $helper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Loomis\Shipping\Helper\Data $helper,
        array $data = []
    )
    {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->helper = $helper;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    private function getItemByRelativeKey($item, $key)
    {
        foreach ($item as $serviceKey => $serviceTmp) {
            if (strpos($serviceKey, $key) !== false) {
                return $serviceTmp;
            }
        }

        return null;
    }

    private function log($message)
    {
        $this->helper->logger()->debug($message);
    }

    private function get_rate_from_service_item_loomis($servicesArray)
    {
        $rateArray = array();

        foreach($servicesArray as $service) {

            $this->log(__METHOD__ . __LINE__  . ' -service item ' . print_r($service, true));
            $serviceCode = $this->getItemByRelativeKey($service, 'shipment');
            $deliverydate = $this->getItemByRelativeKey($serviceCode, 'estimated_delivery_date');
            $shipmentInfo = $this->getItemByRelativeKey($serviceCode, 'shipment_info_num');

            if (empty($shipmentInfo)) {
                continue;
            }

            $cost = '';

            foreach ($shipmentInfo as $info) {
                $infoName = $this->getItemByRelativeKey($info, 'name');

                if ($infoName === 'TOTAL_CHARGE') {
                    $cost = $this->getItemByRelativeKey($info, 'value');
                }
            }

            $on_min_date = date(self::DATE_FORMAT , strtotime($deliverydate));
            $on_max_date = date(self::DATE_FORMAT , strtotime($deliverydate));

            $serviceType = false;

            if (!empty($this->getItemByRelativeKey($serviceCode, 'service_type'))) {
                $serviceType = $this->getItemByRelativeKey($serviceCode, 'service_type');
            }

            $_referenceSerivceLoomisArray = $this->helper->getServiceType();

            if( intval($cost) > 0 && isset($_referenceSerivceLoomisArray[$serviceType])) {
                //$serviceRateArray = array(
                $mergeArray = array(
                    'service_name' => $_referenceSerivceLoomisArray[$serviceType],
                    'service_code' => 'loomis_'. $serviceType,
                    'total_price' => $cost,
                    'currency' => 'CAD',
                    'min_delivery_date' => $on_min_date,
                    'max_delivery_date' => $on_max_date
                );

                array_push($rateArray , $mergeArray);
            }
        }

        return $rateArray;
    }

    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     * @return Result|bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            $this->helper->logger()->debug('loomis inactive');
            return false;
        }
        /** @var Result $result */
        $result = $this->_rateResultFactory->create();
        $xMLData = $this->buildRatingRequest($request);

        $URL = $this->helper->getRatingEndpoint();

        $ch = curl_init($URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "$xMLData");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $response = curl_exec($ch);
        $error_number = curl_errno($ch);
        $error_message = curl_error($ch);

        $this->helper->logger()->debug(__METHOD__ . __LINE__ ." response from $URL". $response);
        // Return an error is cURL has a problem
        if( $error_message ) {
            $this->helper->logger()->debug(__METHOD__ . " Error " . $error_number . ' ' . print_r($response,1));
        }

        curl_close($ch);

        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $response);
        $this->helper->logger()->debug(__METHOD__ . " APi response ". print_r($response,1));
        $xml = new \SimpleXMLElement($response);
        $body = $xml->xpath('////ax29getRatesResult');

        $servicesArray = json_decode(json_encode((array)$body), TRUE);

        $this->helper->logger()->debug(__METHOD__ . __LINE__  . ' -serviceresponse body ' . print_r($body, true));
        $this->helper->logger()->debug(__METHOD__ . __LINE__  . ' -serviceresponse ' . print_r($response, true));
        $rateArray = $this->get_rate_from_service_item_loomis($servicesArray);

        if (!empty($rateArray)) {
            foreach ($rateArray as $rate) {
                $method = $this->createResultMethod($rate);
                $result->append($method);
            }
        }

        return $result;
    }

    private function buildRatingRequest(RateRequest $request)
    {
        $date = date('Ymd');
        $store = $request->getStoreId();
        $password = $this->helper->getLoomisPassword($store);
        $username = $this->helper->getLoomisUsername($store);
        $accountNumber = $this->helper->getLoomisAccountNumber($store);
        $pickupCity = $this->helper->getOriginCity($store);
        $pickupPostcode = $this->helper->getOriginPostcode($store);
        $originRegion = $this->helper->getProvince($store)->getCode();
        $xml_data = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.rating.uss.transforce.ca" xmlns:xsd="http://ws.rating.uss.transforce.ca/xsd" xmlns:xsd1="http://dto.uss.transforce.ca/xsd">
   <soapenv:Header/>
   <soapenv:Body>
      <ws:getRates>
         <ws:request>
            <xsd:password>' . $password . '</xsd:password>
            <xsd:shipment>
               <xsd1:delivery_address_line_1>' . $request->getDestStreet() . '</xsd1:delivery_address_line_1>
               <xsd1:delivery_city>' . $request->getDestCity() . '</xsd1:delivery_city>
               <xsd1:delivery_country>' . $request->getDestCountryId() . '</xsd1:delivery_country>
               <xsd1:delivery_name>Test</xsd1:delivery_name>
               <xsd1:delivery_postal_code>' . $request->getDestPostcode() . '</xsd1:delivery_postal_code>
               <xsd1:delivery_province>' . $request->getDestRegionCode() . '</xsd1:delivery_province>
               <xsd1:dimension_unit>I</xsd1:dimension_unit>
               <xsd1:packages>
                  <xsd1:reported_weight>' . $request->getPackageWeight() . '</xsd1:reported_weight>
               </xsd1:packages>
               <xsd1:pickup_address_line_1>' . 'test' . '</xsd1:pickup_address_line_1>
               <xsd1:pickup_city>' . $pickupCity . '</xsd1:pickup_city>
               <xsd1:pickup_name>test</xsd1:pickup_name>
               <xsd1:pickup_postal_code>' . $pickupPostcode . '</xsd1:pickup_postal_code>
               <xsd1:pickup_province>' . $originRegion . '</xsd1:pickup_province>
               <xsd1:reported_weight_unit>L</xsd1:reported_weight_unit>
               <xsd1:service_type>ALL</xsd1:service_type>
               <xsd1:shipper_num>' . $accountNumber . '</xsd1:shipper_num>
               <xsd1:shipping_date>' . $date . '</xsd1:shipping_date>
            </xsd:shipment>
            <xsd:user_id>' . $username . '</xsd:user_id>
         </ws:request>
      </ws:getRates>
   </soapenv:Body>
</soapenv:Envelope>';

        $this->log('xml request ' . $xml_data);

        return $xml_data;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['loomisrate' => __('Loomis')];
    }

    /**
     * Creates result method
     *
     * @param array $rate
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method
     */
    private function createResultMethod($rate)
    {
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();

        @$method->setData('carrier', $this->getCarrierCode());
        @$method->setData('carrier_title', $rate['service_name']);

        @$method->setData('method', $rate['service_code']);
        @$method->setData('method_title', $this->getConfigData('name'));

        @$method->setData('price', $rate['total_price']);
        @$method->setData('cost', $rate['total_price']);

        $this->helper->logger()->debug('loomis rate');

        return $method;
    }
}
