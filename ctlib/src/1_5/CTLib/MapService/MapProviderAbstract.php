<?php

namespace CTLib\MapService;

use CTLib\Util\CTCurl,
    CTLib\Util\Arr;

abstract class MapProviderAbstract implements MapProviderInterface
{
    const CONNECTION_TIMEOUT = 5;
    const REQUEST_TIMEOUT = 10;

    protected $allowedQualityCodes = array();

    public function __construct($allowedQualityCodes)
    {
        $this->allowedQualityCodes = $allowedQualityCodes;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowedQualityCodes($country = null)
    {
        return $this->allowedQualityCodes;
    }

    /**
     * Build request for geocoding
     *
     * @param CTCurl $request curl request
     * @param mixed $address associative array or string containing address
     * @return void 
     *
     */
    abstract protected function geocodeBuildRequest($request, $address, $country);

    /**
     * Process Result of geocoding
     *
     * @param mixed $result result
     * @return mixed geocode result
     *
     */
    abstract protected function geocodeProcessResult($result);

    /**
     * Implements method in MapProviderInterface
     *
     */    
    public function geocode($address, $country = null)
    {
        if (is_array($address)) {
            Arr::mustHave($address, "street", "city", "postalCode");
        }
        elseif (!is_string($address) || empty($address)) {
            throw new \Exception("address parameter is invalid");
        }

        $curl = $this->createMapServiceRequest();
        $this->geocodeBuildRequest($curl, $address, $country);
        
        $response = $curl->exec();

        $geocodeResult = $this->geocodeProcessResult($response);
        $geocodeResult["isValidated"] 
            = in_array(
                $geocodeResult["qualityCode"],
                $this->allowedQualityCodes
            );
        
        return $geocodeResult;
    }

    /**
     * Build request for batch geocoding
     *
     * @param CTCurl $request curl request
     * @param mixed $address array of associative array or string containing addresses
     * @return void 
     *
     */
    abstract protected function geocodeBatchBuildRequest($request, $addresses, $country);

    /**
     * Process Result of batch geocoding
     *
     * @param mixed $result result
     * @return mixed batch geocode result
     *
     */
    abstract protected function geocodeBatchProcessResult($result);


    /**
     * Implements method in MapProviderInterface
     *
     */    
    public function geocodeBatch(array $addresses, $country = null)
    {
        $curl = $this->createMapServiceRequest();
        $curl->IsBatch = true;
        $this->geocodeBatchBuildRequest($curl, $addresses, $country);
        
        $response = $curl->exec();

        $geocodeResults = $this->geocodeBatchProcessResult($response);

        foreach ($geocodeResults as &$geocodeResult){
            $geocodeResult["isValidated"]
                = in_array(
                    $geocodeResult["qualityCode"], 
                    $this->allowedQualityCodes
                );
        }

        return $geocodeResults;
    }

    /**
     * Build request for reverse geocoding
     *
     * @param CTCurl $request curl request
     * @param float $latitude latitude
     * @param float $longitude longitude
     * @return void 
     *
     */
    abstract protected function reverseGeocodeBuildRequest($request, $latitude, $longitude, $country);

    /**
     * Process Result of reverse geocoding
     *
     * @param mixed $result result
     * @return mixed reverse geocode result
     *
     */
    abstract protected function reverseGeocodeProcessResult($result);
    

    /**
     * Implements method in MapProviderInterface
     *
     */    
    public function reverseGeocode($latitude, $longitude, $country = null)
    {
        $curl = $this->createMapServiceRequest();
        $this->reverseGeocodeBuildRequest($curl, $latitude, $longitude, $country);
        
        $response = $curl->exec();
        $responseResult = $this->reverseGeocodeProcessResult($response);
        
        $responseResult["isValidated"] 
            = in_array(
                $responseResult["qualityCode"],
                $this->allowedQualityCodes
            );

        return $responseResult;
    }


    /**
     * Build request for batch reverse geocoding
     *
     * @param CTCurl $request curl request
     * @param array $latLngs array of array containing lat lng
     * @return void 
     *
     */
    abstract protected function reverseGeocodeBatchBuildRequest($request, array $latLngs, $country);

    /**
     * Process Result of batch reverse geocoding
     *
     * @param mixed $result result
     * @return mixed batch reverse geocode result
     *
     */
    abstract protected function reverseGeocodeBatchProcessResult($result);


    /**
     * Implements method in MapProviderInterface
     *
     */    
    public function reverseGeocodeBatch(array $latLngs, $country = null)
    {
        $curl = $this->createMapServiceRequest();
        $curl->IsBatch = true;
        $this->reverseGeocodeBatchBuildRequest($curl, $latLngs, $country);
        
        $response = $curl->exec();

        return $this->reverseGeocodeBatchProcessResult($response);
    }


    /**
     * Build request for routing
     *
     * @param CTCurl $request curl request
     * @param float $fromLatitude origin lat
     * @param float $fromLongitude origin lng
     * @param float $toLatitude destination lat
     * @param float $toLongitude destination lng
     * @param string $optimizeBy mapquest route type
     * @param array $options
     * @return void 
     *
     */
    abstract protected function routeBuildRequest($request, $fromLatitude, $fromLongitude, $toLatitude, $toLongitude, $optimizeBy, $options, $country);
    
    /**
     * Process Result of routing
     *
     * @param mixed $result result
     * @param string $optimizeBy mapquest route type
     * @return mixed batch routing
     *
     */
    abstract protected function routeProcessResult($result, $optimizeBy);

    /**
     * Process Result of routing for time and distance only.
     *
     * @param mixed $result result
     * @param string $optimizeBy mapquest route type
     * @return array                array($time, $distance)
     *                              $time in seconds
     *                              $distance in country-specific unit.
     */
    abstract protected function routeTimeAndDistanceProcessResult($result, $optimizeBy);

    /**
     * Implements method in MapProviderInterface
     *
     */    
    public function route($fromLatitude, $fromLongitude, $toLatitude, $toLongitude, $optimizeBy, array $options, $country = null)
    {
        $curl = $this->createMapServiceRequest();
        $this->routeBuildRequest($curl, $fromLatitude, $fromLongitude, $toLatitude, $toLongitude, $optimizeBy, $options, $country);
        
        $response = $curl->exec();

        return $this->routeProcessResult($response, $optimizeBy);
    }

    /**
     * @inherit
     */
    public function routeTimeAndDistance($fromLatitude, $fromLongitude,
        $toLatitude, $toLongitude, $optimizeBy, array $options=array(), $country=null)
    {
        $curl = $this->createMapServiceRequest();
        $this->routeBuildRequest(
            $curl,
            $fromLatitude,
            $fromLongitude,
            $toLatitude,
            $toLongitude,
            $optimizeBy,
            $options,
            $country
        );
        $response = $curl->exec();
        return $this->routeTimeAndDistanceProcessResult($response, $optimizeBy);
    }

    /**
     * Create a curl object to use
     *
     * @return CTCurl
     *
     */
    protected function createMapServiceRequest()
    {
        $curl = new CTCurl();
        $curl->FailOnError = true;
        $curl->SSL_VerifyHost = 0;
        $curl->SSL_VerifyPeer = false;
        $curl->ConnectTimeout = static::CONNECTION_TIMEOUT;
        $curl->Timeout = static::REQUEST_TIMEOUT;
        $curl->Method = CTCurl::REQUEST_POST;

        return $curl;
    }

}
