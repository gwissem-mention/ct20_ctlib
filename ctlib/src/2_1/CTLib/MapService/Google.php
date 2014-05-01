<?php

namespace CTLib\MapService;

use CTLib\Util\Arr;

class Google implements Geocoder, ReverseGeocoder
{   
    const CONNECTION_TIMEOUT = 5;
    const REQUEST_TIMEOUT = 10;
    
    /**
     * @var string
     */
    protected $url;
    
    public function __construct($url, $key, $logger)
    {
        $this->url = $url;
        $this->key = $key;
        $this->logger = $logger;
    }
    
    /**
     * Implements method in Geocoder
     *
     */
    public function geocode($address, $allowedQualityCodes)
    {
        $requestData = $this->buildGeocodeRequestData($address);
        $response = $this->getGeocodeResponse($requestData);
        $this->logger->debug("Google: geocode response is {$response}.");
        
        $decodedResult = json_decode($response, true);
        if (! $this->isValidResponse($decodedResult, $errorMsg)) {
            throw new \exception("Google: invalid geocode response with error {$errorMsg}");
        }
        
        $geocodeResult = Arr::findByKeyChain(
                        $decodedResult, 
                        "results.0");
        
        if (empty($geocodeResult)) {
            throw new \Exception("Google: geocode result is invalid");
        }
        
        $geocodeResult = $this->normalizeGoogleResult($geocodeResult);
        
        if (in_array($geocodeResult["qualityCode"], $allowedQualityCodes)) {
            $geocodeResult['isValidated'] = true;
        }
        else {
            $geocodeResult['isValidated'] = false;
        }
        
        return $geocodeResult;
    }

    /**
     * Implements method in ReverseGeocoder
     *
     */    
    public function reverseGeocode($latitude, $longitude)
    {
        $response = $this->getReverseGeocodeResponse($latitude, $longitude);
        $this->logger->debug("Google: reverse geocode response is {$response}.");
        
        $decodedResult = json_decode($response, true);
        if (! $this->isValidResponse($decodedResult, $errorMsg)) {
            throw new \exception("Google: invalid reverse geocode response with error {$errorMsg}");
        }
        
        $reverseGeocodeResult = Arr::findByKeyChain(
                                $decodedResult,
                                "results.0");
        
        if (empty($reverseGeocodeResult)) {
            throw new \Exception("Google: revese geocode result is invalid");
        }
        
        $reverseGeocodeResult = $this->
                            normalizeGoogleResult($reverseGeocodeResult);
        
        $reverseGeocodeResult['isValidated'] = true;
        
        return $reverseGeocodeResult;
    }
    
    /**
     * Build Address Request array sending to google
     *
     * @param array $address contains address compenents 
     * @return formatted request data for google
     *
     */
    protected function buildGeocodeRequestData($address)
    {
        $addressStr = '';
        foreach ($address as $component => $value) {
            if (Arr::get($component, $address)) {
                $addressStr .= $value . " ";
            }
        }
        
        $requestData = str_replace(' ', '+', $addressStr);
        return $requestData;
    }
    
    /**
     * Get Geocode Response array from google
     *
     * @param $requestData contains address
     * @return curl execution result This is the return value description
     *
     */    
    protected function getGeocodeResponse($requestData) 
    {
        $curl = $this->createMapServiceRequest();
        $requestUrl = $this->url . "geocode/json?address=". $requestData . 
            "&sensor=false&key=". $this->key;
        curl_setopt($curl, CURLOPT_URL, $requestUrl);
        
        $response = curl_exec($curl);
        
        if (! $response) {
            $errorCode = curl_errno($curl);
            throw new \Exception("Google: failed on http request error. {$errorCode}");
        }
        
        return curl_exec($curl);
    }
    
    /**
     * Get Reverse geocode response array from google
     *
     * @param $latitude contains latitude
     * @param $longitude contains longitude
     * @return curl execution result This is the return value description
     *
     */    
    protected function getReverseGeocodeResponse($latitude, $longitude)
    {
        $curl = $this->createMapServiceRequest();
        $requestData = $latitude . "," . $longitude;
        $requestUrl = $this->url . "geocode/json?latlng=". $requestData . 
            "&sensor=false&key=". $this->key;
        
        curl_setopt($curl, CURLOPT_URL, $requestUrl);
        
        $response = curl_exec($curl);
        
        if (! $response) {
            $errorCode = curl_errno($curl);
            throw new \Exception("Google: failed on http request error. {$errorCode}");
        }
        
        return curl_exec($curl);
    }
    
    /**
     * Build Address array From Google Result
     *
     * @param array $result response result from google
     * @return array address array
     *
     */
    protected function normalizeGoogleResult($result)
    {   
        $addressComponents = Arr::mustGet("address_components", $result);
        
        foreach ($addressComponents as $component) {
            if (in_array("street_number", $component['types'])) {
                $addressComponents['street_number'] = $component['long_name'];
            }
            if (in_array("route", $component['types'])) {
                $addressComponents['street'] = $component['long_name'];
            }
            if (in_array("locality", $component['types'])) {
                $addressComponents['city'] = $component['long_name'];
            }
            if (in_array("administrative_area_level_1", $component['types'])) {
                $addressComponents['subdivision'] = $component['long_name'];
            }
            if (in_array("administrative_area_level_2", $component['types'])) {
                $addressComponents['district'] = $component['long_name'];
            }
            if (in_array("administrative_area_level_3", $component['types'])) {
                $addressComponents['locality'] = $component['long_name'];
            }
            if (in_array("country", $component['types'])) {
                $addressComponents['country'] = $component['long_name'];
            }
            if (in_array("postal_code", $component['types'])) {
                $addressComponents['postalCode'] = $component['long_name'];
            }
        }
        
        return array(
            "qualityCode" => Arr::findByKeyChain($result, "geometry.location_type"),
            "street"      => Arr::findByKeyChain($addressComponents, "street_number") . " " .
                             Arr::findByKeyChain($addressComponents, "street"),
            "city"        => Arr::findByKeyChain($addressComponents, "city"),
            "district"    => Arr::findByKeyChain($addressComponents, "district"),
            "locality"    => Arr::findByKeyChain($addressComponents, "locality"),
            "subdivision" => Arr::findByKeyChain($addressComponents, "subdivision"),
            "postalCode"  => Arr::findByKeyChain($addressComponents, "postalCode"),
            "country"     => Arr::findByKeyChain($addressComponents, "country"),
            "mapUrl"      => null,
            "lat"         => Arr::findByKeyChain($result, "geometry.location.lat"),
            "lng"         => Arr::findByKeyChain($result, "geometry.location.lng")
            );
    }
    
    /**
    * Check if response is valid or not
    * @param array $decodedResponse json decoded results
    * @return true/flase if response is invalid
    *
    */    
    protected function isValidResponse($decodedResponse, &$errorMsg=null)
    {
        if (is_null($decodedResponse)) {
            $jsonDecodeError = json_last_error();
            $errorMsg = "Google: response is not valid JSON with error {$jsonDecodeError}";
            return false;
        }
        
        $status = Arr::findByKeyChain(
            $decodedResponse, 
            "status");
        $this->logger->debug("Google: reverse geocode response status is {$status}.");
        
        if ($status != 'OK') {
            $errorMsg = "Google: request failed because of {$status}";
            return false;
        }
        
        return true;
    }
    
    /**
    * Build curl to map service
    *
    * @param string $path specific service url
    * @return php curl
    *
    */    
    protected function createMapServiceRequest()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // Do not need to verify ssl
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, static::CONNECTION_TIMEOUT);
        curl_setopt($curl, CURLOPT_TIMEOUT, static::REQUEST_TIMEOUT);
        
        return $curl;
    }
}