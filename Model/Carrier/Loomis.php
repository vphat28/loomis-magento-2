<?php

namespace Loomis\Shipping\Model\Carrier;

use Magento\Framework\DataObject;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Shipment\Request;

class Loomis extends AbstractCarrierOnline implements CarrierInterface
{
    const DATE_FORMAT = "Y-m-d H:i:s O";
    /**
     * @var string
     */
    protected $_code = 'loomisrate';

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $_rateMethodFactory;

    /** @var \Loomis\Shipping\Helper\Data */
    private $helper;

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory, \Psr\Log\LoggerInterface $logger, Security $xmlSecurity, \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory, \Magento\Shipping\Model\Rate\ResultFactory $rateFactory, \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory, \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory, \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory, \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory, \Magento\Directory\Model\RegionFactory $regionFactory, \Magento\Directory\Model\CountryFactory $countryFactory, \Magento\Directory\Model\CurrencyFactory $currencyFactory, \Magento\Directory\Helper\Data $directoryData, \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
                                \Loomis\Shipping\Helper\Data  $helper,
                                array $data = [])
    {
        $this->helper = $helper;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $xmlSecurity, $xmlElFactory, $rateFactory, $rateMethodFactory, $trackFactory, $trackErrorFactory, $trackStatusFactory, $regionFactory, $countryFactory, $currencyFactory, $directoryData, $stockRegistry, $data);
    }

    /**
     * Processing additional validation to check is carrier applicable.
     *
     * @param \Magento\Framework\DataObject $request
     * @return $this|bool|\Magento\Framework\DataObject
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @since 100.2.6
     */
    public function processAdditionalValidation(\Magento\Framework\DataObject $request)
    {
        return $this;
    }

    /**
     * Return container types of carrier
     *
     * @param \Magento\Framework\DataObject|null $params
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getContainerTypes(\Magento\Framework\DataObject $params = null)
    {
        return [
            'normal_package' => 'Normal Package',
            'insurance_package' => 'Insurance Package',
        ];
    }

    public function canCollectRates()
    {
        return true;
    }

    private function getItemByRelativeKey($item, $key)
    {
        if (!empty($item)) {
            foreach ($item as $serviceKey => $serviceTmp) {
                if (strpos($serviceKey, $key) !== false) {
                    return $serviceTmp;
                }
            }
        }

        return null;
    }

    public function getTrackingInfo($number)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.addons.uss.transforce.ca" xmlns:xsd="http://ws.addons.uss.transforce.ca/xsd">
   <soapenv:Header/>
   <soapenv:Body>
      <ws:trackByBarcode>
         <ws:request>
            <xsd:barcode>'.$number.'</xsd:barcode>
            <xsd:track_shipment>TRUE</xsd:track_shipment>
         </ws:request>
      </ws:trackByBarcode>
   </soapenv:Body>
</soapenv:Envelope>';

        $response = $this->curlRequest($this->helper->getAddonEndpoint(), $xml);

        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $response);
        $xml = new \SimpleXMLElement($response);
        $body = $xml->xpath('////ax21result');

        $servicesArray = json_decode(json_encode((array)$body), TRUE);
        $this->helper->logger()->debug(__METHOD__ . __LINE__  . ' -serviceresponse ' . print_r($servicesArray, true));

        if (isset($servicesArray[0]['ax23pin'])) {
            $progress = [];
            if (isset($servicesArray[0]['ax23events'])) {

                foreach ($servicesArray[0]['ax23events'] as $event) {
                    $newProgress = [];
                    $localDateTime = explode(' ', $event['ax23local_date_time']);
                    $newProgress['deliverydate'] = $localDateTime[0];
                    $newProgress['deliverytime'] = $localDateTime[1];
                    $newProgress['deliverylocation'] = $localDateTime[1];
                    $newProgress['activity'] = $event['ax23code_description_en'];
                    $address = [];

                    foreach (
                        [
                            'ax23address_line_1',
                            'ax23address_line_2',
                            'ax23address_line_3',
                            'ax23city',
                            'ax23postal_code',
                            'ax23province',
                            'ax23country',
                        ] as $type) {
                        if (!empty($event["ax23address"][$type])) {
                            $address[] = $event["ax23address"][$type];
                        }
                    }
                    $newProgress['deliverylocation'] = implode(', ', $address);
                    $progress[] = $newProgress;
                }
            }

            // success, now return tracking info to view
            $data = $servicesArray[0];
            $track = new DataObject();
            $track->setData('tracking', $data['ax23pin']);
//            $track->setData('url', $data['ax23tracking_url_en']);
            /*
             * $fields = [
    'Status' => 'getStatus',
    'Signed by' => 'getSignedby',
    'Delivered to' => 'getDeliveryLocation',
    'Shipped or billed on' => 'getShippedDate',
    'Service Type' => 'getService',
    'Weight' => 'getWeight',
];
             */
            $track->setData('status', $data['ax23delivered'] === 'false' ?  __('Undelivered') : __('Delivered'));
            $track->setData('signed_by', $data['ax23signed_by']);

            $track->setData('progressdetail', $progress);
            return $track;
        }
    }

    /**
     * Check if carrier has shipping tracking option available
     *
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Check if carrier has shipping label option available
     *
     * @return bool
     */
    public function isShippingLabelsAvailable()
    {
        return true;
    }

    private function log($message)
    {
        $this->helper->logger()->debug($message);
    }
    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
     *
     * @param DataObject|Request $request
     * @return DataObject
     * @see requestToShipment
     */
    protected function _doShipmentRequest(DataObject $request)
    {
        $result = new \Magento\Framework\DataObject();
        $xml = $this->buildShipmentRequest($request);

        try {
             $response = $this->curlRequest($this->helper->getShippingEndpoint(), $xml);

            $this->log('create shipment api response' . $response);

            $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $response);
            $this->helper->logger()->debug(__METHOD__ . " APi response ". print_r($response,1));
            $xml = new \SimpleXMLElement($response);
            $body = $xml->xpath('////ax25processShipmentResult');

            $servicesArray = json_decode(json_encode((array)$body), TRUE);
            $this->helper->logger()->debug(__METHOD__ . __LINE__  . ' -serviceresponse ' . print_r($servicesArray, true));

        } catch (\Throwable $e) {

        }

        if (isset($servicesArray[0]['ax27shipment'])) {
            $servicesArray = $servicesArray[0]['ax27shipment'];
        } else {
            $result->setErrors($e->getMessage());
        }

        $this->_debug($response);

        if ($result->hasErrors() || empty($response)) {
            return $result;
        } else {
            return $this->_sendShipmentAcceptRequest($servicesArray);
        }
    }

    private function buildLabelXML($trackId, $store = null)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.business.uss.transforce.ca" xmlns:xsd="http://ws.business.uss.transforce.ca/xsd">
   <soapenv:Header/>
   <soapenv:Body>
      <ws:getLabelsAdvancedV2>
         <ws:request>
            <xsd:format>PNG</xsd:format>
            <xsd:id>'. $trackId .'</xsd:id>
            <xsd:password>'. $this->helper->getLoomisPassword($store) .'</xsd:password>
            <xsd:user_id>'. $this->helper->getLoomisUsername($store) .'</xsd:user_id>
         </ws:request>
      </ws:getLabelsAdvancedV2>
   </soapenv:Body>
</soapenv:Envelope>';

        $this->log('Create label xml '. $xml);

        return $xml;
    }

    private function getLabelContent($loomisShipmentId)
    {
        $xml = $this->buildLabelXML($loomisShipmentId);
        $response = $this->curlRequest($this->helper->getShippingEndpoint(), $xml);

        $this->log('create label api response' . $response);

        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $response);
        $this->helper->logger()->debug(__METHOD__ . " APi response ". print_r($response,1));

        $xml = new \SimpleXMLElement($response);
        $body = $xml->xpath('////ax25result');

        $servicesArray = json_decode(json_encode((array)$body), TRUE);
        $this->helper->logger()->debug(__METHOD__ . __LINE__  . ' -serviceresponse ' . print_r($servicesArray, true));

        if (isset($servicesArray[0]['ax25label'])) {
            return $servicesArray[0]['ax25label'];
        }

        return '';
    }

    public function _sendShipmentAcceptRequest($servicesArray)
    {
        $result = new \Magento\Framework\DataObject();

        if (isset($servicesArray['ax27id'])) {
            $trackingNumber = '';
            $SIN = '';
            foreach ($servicesArray["ax27shipment_info_str"] as $info) {
                if ($info['ax27name'] === "SIN") {
                    $SIN = $info['ax27value'];
                }
            }

            $trackingNumber = (string)$servicesArray['ax27id'];
            $shippingLabelContent = $this->getLabelContent($trackingNumber);
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $result->setShippingLabelContent(base64_decode($shippingLabelContent));
            $result->setTrackingNumber($SIN);
        } else {
            $result->setErrors((string)__('Can not create shipment. Please contact developer'));
        }
        return $result;
    }

    /**
     * Do return of shipment.
     *
     * Implementation must be in overridden method
     *
     * @param Request $request
     * @return \Magento\Framework\DataObject
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function returnOfShipment($request)
    {
        $this->log(__FILE__ . __LINE__ . ' Trying to void shipment');
        return new \Magento\Framework\DataObject();
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

    private function curlRequest($URL, $xMLData)
    {
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

        return $response;
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
        $result = $this->_rateFactory->create();
        $xMLData = $this->buildRatingRequest($request);

        $URL = $this->helper->getRatingEndpoint();

        $response = $this->curlRequest($URL, $xMLData);

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
                $rate['insurance'] = true;
                $method = $this->createResultMethod($rate);
                $result->append($method);
            }
        }

        return $result;
    }

    public function getDeliveryConfirmationTypes(?DataObject $params = NULL)
    {
        $types = [];

        return $types;
    }

    /**
     * @param Request $request
     * @return string
     */
    private function buildShipmentRequest(Request $request)
    {
        $orderIncrementId = $request->getOrderShipment()->getOrder()->getIncrementId();
        $packageParams = $request->getData('package_params');
        $packages = $request->getData('packages');
        $containerType = $packageParams->getData('container');
        $weightUnit = $packageParams->getData('weight_units') === 'POUND' ? 'L' : 'K';

        if ($containerType == 'insurance_package' && (float)$packageParams->getData('customs_value') < 500) {
            $customValue = 500;
        } else {
            $customValue = $packageParams->getData('custom_value');
        }

        if (!empty($packageParams->getData('customs_value'))) {
            $customValue = $packageParams->getData('customs_value');
        }

        $dimensionUnits = $packageParams->getData('dimension_units') === 'INCH' ? 'I' : 'M';
        $date = date('Ymd');
        $methodCode = explode('_', $request->getShippingMethod());
        $serviceType = $methodCode[1];

        $store = $request->getOrderShipment()->getStore();

        $deliveryAddress = $request->getRecipientAddressStreet();
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="http://ws.business.uss.transforce.ca" xmlns:xsd="http://ws.business.uss.transforce.ca/xsd" xmlns:xsd1="http://dto.uss.transforce.ca/xsd">
   <soapenv:Header/>
   <soapenv:Body>
      <ws:processShipment>
         <ws:request>
            <xsd:user_id>'. $this->helper->getLoomisUsername($store).'</xsd:user_id>
            <xsd:password>'. $this->helper->getLoomisPassword($store).'</xsd:password>
            <xsd:shipment>
               <xsd1:shipper_num>'. $this->helper->getLoomisAccountNumber($store).'</xsd1:shipper_num>
               <xsd1:shipping_date>' . $date . '</xsd1:shipping_date>
               <xsd1:delivery_address_line_1>' . $deliveryAddress . '</xsd1:delivery_address_line_1>
               <xsd1:delivery_address_line_2>' .  $request->getRecipientAddressStreet2() . '</xsd1:delivery_address_line_2>
               <xsd1:delivery_address_line_3/>
               <xsd1:delivery_city>' .  $request->getRecipientAddressCity() .'</xsd1:delivery_city>
               <xsd1:delivery_country>' .  $request->getRecipientAddressCountryCode() .'</xsd1:delivery_country>
               <xsd1:delivery_email>'. $request->getData('recipient_email') .'</xsd1:delivery_email>
               <xsd1:delivery_extension/>
               <xsd1:delivery_name>' .  $request->getRecipientContactPersonName() .'</xsd1:delivery_name>
               <xsd1:delivery_phone>' .  $request->getRecipientContactPhoneNumber() .'</xsd1:delivery_phone>
               <xsd1:delivery_postal_code>' .  $request->getRecipientAddressPostalCode() .'</xsd1:delivery_postal_code>
               <xsd1:delivery_province>' .  $request->getRecipientAddressStateOrProvinceCode() .'</xsd1:delivery_province>
               <xsd1:dimension_unit>'. $dimensionUnits.'</xsd1:dimension_unit>
               <xsd1:pickup_address_line_1>' .  $request->getShipperAddressStreet1() .'</xsd1:pickup_address_line_1>
               <xsd1:pickup_address_line_2>' .  $request->getShipperAddressStreet2() .'</xsd1:pickup_address_line_2>
               <xsd1:pickup_address_line_3/>
               <xsd1:pickup_city>' .  $request->getShipperAddressCity() .'</xsd1:pickup_city>
               <xsd1:pickup_email/>
               <xsd1:pickup_extension/>
               <xsd1:pickup_name>' .  $request->getShipperContactPersonName() .'</xsd1:pickup_name> 
               <xsd1:pickup_postal_code>' .  $request->getShipperAddressPostalCode() .'</xsd1:pickup_postal_code>
               <xsd1:pickup_province>' .  $request->getShipperAddressStateOrProvinceCode() .'</xsd1:pickup_province>
               <xsd1:reported_weight_unit>'. $weightUnit .'</xsd1:reported_weight_unit>
               <xsd1:service_type>' .  $serviceType .'</xsd1:service_type>
              <xsd1:shipment_info_num>
                  <xsd1:name>declared_value</xsd1:name>
                  <xsd1:value>'. $customValue .'</xsd1:value>
               </xsd1:shipment_info_num>       
               ';
        foreach ($packages as $package) {
            $params = $package['params'];
            $xml_data .= '
               <xsd1:packages> 
                  <xsd1:package_info_num>
                     <xsd1:name>LENGTH</xsd1:name>
                     <xsd1:value>' . $params['length'] . '</xsd1:value>
                  </xsd1:package_info_num>
                  <xsd1:package_info_num>
                     <xsd1:name>WIDTH</xsd1:name>
                     <xsd1:value>' . $params['width'] . '</xsd1:value>
                  </xsd1:package_info_num>
                  <xsd1:package_info_num>
                     <xsd1:name>HEIGHT</xsd1:name>
                     <xsd1:value>' . $params['height'] . '</xsd1:value>
                  </xsd1:package_info_num>
                  <xsd1:package_info_str>
                     <xsd1:name>NON_STANDARD</xsd1:name>
                     <xsd1:value>FALSE</xsd1:value>
                  </xsd1:package_info_str>
                  <xsd1:package_info_str>
                     <xsd1:name>SPECIAL_HANDLING</xsd1:name>
                     <xsd1:value>FALSE</xsd1:value>
                  </xsd1:package_info_str>
                  <xsd1:package_info_str>
                     <xsd1:name>REFERENCE</xsd1:name>
                     <xsd1:value>' . $orderIncrementId . '</xsd1:value>
                  </xsd1:package_info_str>
                  <xsd1:reported_weight>' . $params['weight'] . '</xsd1:reported_weight>
               </xsd1:packages>';
        }
        $xml_data .= '
            </xsd:shipment>
         </ws:request>
      </ws:processShipment>
   </soapenv:Body>
</soapenv:Envelope>';


        return $xml_data;
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
        $configuredWeightUnit = $this->helper->getWeightUnit($store);

        if ($configuredWeightUnit === 'lbs') {
            $weightUnit = 'L';
        } else if ($configuredWeightUnit === 'kgs') {
            $weightUnit = 'K';
        } else {
            $weightUnit = 'L';
        }
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
               <xsd1:dimension_unit>M</xsd1:dimension_unit>
               <xsd1:packages>
                  <xsd1:reported_weight>' . $request->getPackageWeight() . '</xsd1:reported_weight>
               </xsd1:packages>
               <xsd1:pickup_address_line_1>' . 'test' . '</xsd1:pickup_address_line_1>
               <xsd1:pickup_city>' . $pickupCity . '</xsd1:pickup_city>
               <xsd1:pickup_name>test</xsd1:pickup_name>
               <xsd1:pickup_postal_code>' . $pickupPostcode . '</xsd1:pickup_postal_code>
               <xsd1:pickup_province>' . $originRegion . '</xsd1:pickup_province>
               <xsd1:reported_weight_unit>'. $weightUnit .'</xsd1:reported_weight_unit>
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

        if (isset($rate['insurance'])) {
            @$method->setData('method', $rate['service_code'] . '_insuranceoption');
            @$method->setData('method_title', $this->getConfigData('name') . ' + ' . __('Insurance'));
        } else {
            @$method->setData('method', $rate['service_code']);
            @$method->setData('method_title', $this->getConfigData('name'));
        }

        @$method->setData('price', $rate['total_price']);
        @$method->setData('cost', $rate['total_price']);

        $this->helper->logger()->debug('loomis rate');

        return $method;
    }
}
