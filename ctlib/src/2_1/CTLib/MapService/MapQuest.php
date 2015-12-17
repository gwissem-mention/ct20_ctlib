<?php

namespace CTLib\MapService;

use CTLib\Util\Arr;

class MapQuest implements Geocoder, BatchGeocoder, ReverseGeocoder, Router
{
    const CONNECTION_TIMEOUT = 5;
    const REQUEST_TIMEOUT = 10;

    const MILES         = 'm';
    const KILOMETERS    = 'k';

    /**
     * @var string
     */
    protected $url;

    public function __construct($url, $key, $logger)
    {
        $this->url    = $url;
        $this->key    = $key;
        $this->logger = $logger;
    }

    /**
     * Implements method in GeoCodeInterface
     *
     */
    public function geocode($address, $allowedQualityCodes)
    {
        $requestData = $this->buildGeocodeRequestData($address);

        $response = $this->getGeocodeResponse($requestData);
        $this->logger->debug("Mapquest: geocode response is {$response}.");

        $decodedResult = json_decode($response, true);
        if (! $this->isValidResponse($decodedResult, $errorMsg)) {
            throw new \exception("Mapquest invalid route response with error {$errorMsg}");
        }

        $geocodeResult = Arr::findByKeyChain(
            $decodedResult,
            "results.0.locations.0");

        if (empty($geocodeResult)) {
            throw new \Exception("Mapquest: geocode result is invalid.");
        }

        $geocodeResult = $this->normalizeGeocodeResult($geocodeResult);

        if (in_array($geocodeResult["qualityCode"], $allowedQualityCodes)) {
            $geocodeResult['isValidated'] = true;
        }
        else {
            $geocodeResult['isValidated'] = false;
        }

        return $geocodeResult;
    }

    /**
     * Implements method in BtachGeoCoder
     *
     */
    public function geocodeBatch(array $addresses, $allowedQualityCodes, $batchSize)
    {
        $geocodeResults = array();
        for ($i = 0, $count=count($addresses); $i < $count; $i += $batchSize) {
            $batchData = array();
            $batchData = array_slice($addresses, $i, $batchSize, true);
            //get indexes from batch data, indexes are using to return results with
            //the same order
            $indexes = array_keys($batchData);
            $this->logger->debug("Mapquest: batch geocode i is {$i}.");

            $requestData = array_map([$this, 'buildGeocodeRequestData'], $batchData);
            $response = $this->getBatchGeocodeResponse($requestData);
            $this->logger->debug("Mapquest: batch geocode response is {$response}.");

            $decodedResult = json_decode($response, true);

            if (! $this->isValidResponse($decodedResult, $errorMsg)) {
                throw new \exception("Mapquest invalid route response with error {$errorMsg}");
            }

            $batchResults = Arr::mustGet("results", $decodedResult);

            foreach ($batchResults as $order => $result) {
                $geocodeResult = Arr::findByKeyChain($result, "locations.0");

                if (empty($geocodeResult)) {
                    throw new \Exception("Mapquest geocode batch result is invalid.");
                }

                $normalizedResult = $this->normalizeGeocodeResult($geocodeResult);

                if (in_array($normalizedResult["qualityCode"], $allowedQualityCodes)) {
                    $normalizedResult['isValidated'] = true;
                }
                else {
                    $normalizedResult['isValidated'] = false;
                }

                $geocodeResults[$indexes[$order]] = $normalizedResult;
            }
        }

        return $geocodeResults;
    }

    /**
     * Implements method in ReverseGeoCoder
     *
     */
    public function reverseGeocode($latitude, $longitude)
    {
        $response = $this->getReverseGeocodeResponse($latitude, $longitude);
        $this->logger->debug("Mapquest: reverse geocode response is {$response}.");

        $decodedResult = json_decode($response, true);
        if (! $this->isValidResponse($decodedResult, $errorMsg)) {
            throw new \exception("Mapquest invalid route response with error {$errorMsg}");
        }

        $reverseGeocodeResult = Arr::findByKeyChain(
            $decodedResult,
            "results.0.locations.0");

        if (empty($reverseGeocodeResult)) {
            throw new \Exception("Mapquest: reverse geocode result is invalid.");
        }

        $reverseGeocodeResult = $this->
            normalizeGeocodeResult($reverseGeocodeResult);

        $reverseGeocodeResult['isValidated'] = true;

        return $reverseGeocodeResult;
    }

    /**
     * Implements method in Router
     *
     */
    public function route($fromLatitude, $fromLongitude, 
        $toLatitude, $toLongitude, $optimizeBy, array $options, $distanceUnit)
    {
        $requestData = $this->buildRouteRequestData(
            $fromLatitude,
            $fromLongitude,
            $toLatitude,
            $toLongitude,
            $optimizeBy,
            $options,
            $distanceUnit
        );

        $response = $this->getRouteResponse($requestData);

        $decodedResult = json_decode($response, true);
        if (! $this->isValidResponse($decodedResult, $errorMsg)) {
            throw new \exception("Mapquest invalid route response with error {$errorMsg}");
        }

        $routeResult = Arr::get("route", $decodedResult);
        if (empty($routeResult)) {
            throw new \Exception("Mapquest: route result is invalid");
        }

        $routeResult = $this->normalizeRouteResult($routeResult);

        return $routeResult;
    }

    /**
     * Implements method in Router
     *
     */
    public function routeTimeAndDistance($fromLatitude, $fromLongitude,
        $toLatitude, $toLongitude, $optimizeBy, array $options, $distanceUnit)
    {
        $requestData = $this->buildRouteRequestData(
            $fromLatitude,
            $fromLongitude,
            $toLatitude,
            $toLongitude,
            $optimizeBy,
            $options,
            $distanceUnit
        );

        $response = $this->getRouteResponse($requestData);

        $decodedResult = json_decode($response, true);

        if (! $this->isValidResponse($decodedResult, $errorMsg)) {
            throw new \exception("Mapquest invalid route response with error {$errorMsg}");
        }

        $routeResult = Arr::get("route", $decodedResult);
        if (empty($routeResult)) {
            throw new \Exception("Mapquest: route result is invalid");
        }

        $routeResult = array(Arr::get("time", $routeResult), Arr::get("distance", $routeResult));

        return $routeResult;
    }

    /**
     * Return mapquest javascript api url
     */
    public function getJavascriptApiUrl()
    {
        return "https://www.mapquestapi.com/sdk/js/v7.0.s/mqa.toolkit.js?key=" . $this->key;
    }

    /**
     * Return mapquest javascript plugin
     */
    public function getJavascriptMapPlugin()
    {
        return "mapquest.maps.plugin.js";
    }

    /**
     * Build Address Request array sending to mapquest
     *
     * @param array $address contains address compenents
     * @return formatted request data for mapquest
     *
     */
    protected function buildGeocodeRequestData($address)
    {
        $street = Arr::get("street1", $address);
        if (Arr::get("street2", $address)) {
            $street .= ", " . Arr::get("street2", $address);
        }
        if (Arr::get("street3", $address)) {
            $street .= ", " . Arr::get("street3", $address);
        }

        $requestData = array(
            "street"     => $street,
            "adminArea5" => Arr::get("city", $address),
            "adminArea3" => Arr::get("subdivision", $address),
            "district"   => Arr::get("adminArea4", $address),
            "postalCode" => Arr::get("postalCode", $address),
            "adminArea1" => Arr::get("countryCode", $address)
        );

        return $requestData;
    }

    /**
     * Get geocode response array from mapquest
     *
     * @param $requestData contains address
     * @return curl execution result
     *
     */
    protected function getGeocodeResponse($requestData)
    {
        $path = "geocoding/v1/address?key=". $this->key;
        $curl = $this->createMapServiceRequest($path);

        $postData = 'json=' . urlencode(json_encode(array('location' => $requestData)));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

        $response = curl_exec($curl);

        if (! $response) {
            $errorCode = curl_errno($curl);
            throw new \Exception("Mapquest: failed on http request error {$errorCode}.");
        }

        return $response;
    }

    /**
     * Get batch geocode response array from mapquest
     *
     * @param $requestData contains a list of addresses
     * @return curl execution result
     *
     */
    protected function getBatchGeocodeResponse($requestData)
    {
        $path = "geocoding/v1/batch?key=". $this->key;
        $curl = $this->createMapServiceRequest($path);

        $postData = 'json=' .  urlencode(
                json_encode(
                    array(
                        'locations' => array_values($requestData)
                    )));

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

        $response = curl_exec($curl);

        if (! $response) {
            $errorCode = curl_errno($curl);
            throw new \Exception("Mapquest: failed on http request error {$errorCode}.");
        }

        return $response;
    }

    /**
     * Get Reverse geocode response array from google
     *
     * @param $latitude contains latitude
     * @param $longitude contains longitude
     * @return curl execution result
     *
     */
    protected function getReverseGeocodeResponse($latitude, $longitude)
    {
        $path = "geocoding/v1/reverse?key=". $this->key;
        $curl = $this->createMapServiceRequest($path);

        $requestData = array(
            'lat' => $latitude,
            "lng" => $longitude
        );

        $requestData = http_build_query($requestData);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);

        $response = curl_exec($curl);

        if (! $response) {
            $errorCode = curl_errno($curl);
            throw new \Exception("Mapquest: failed on http request error {$errorCode}.");
        }

        return $response;
    }

    /**
     * Build request data array sending to mapquest
     *
     * @param $fromLatitude origin latitude
     * @param $fromLongitude origin longitude
     * @param $toLatitude destination latitude
     * @param $toLongitude destination longitude
     * @param $optimizeBy route request type
     * @param $options route avoid options
     * @param $distanceUnit distance unit by country
     * @return formatted request data string for mapquest
     *
     */
    protected function buildRouteRequestData($fromLatitude, $fromLongitude, 
        $toLatitude, $toLongitude, $optimizeBy, $options, $distanceUnit)
    {
        switch ($distanceUnit) {
            case 'kilometer':
                $unit = self::KILOMETERS;
                break;
            default:
                $unit = self::MILES;
        }

        // if avoid types is set
        $avoidTypes = '';
        if ($options != null) {
            $avoidTypes = $this->convertRouteAvoidOptions($options);
        }

        $requestData =
            array(
                "from"      => $fromLatitude . "," . $fromLongitude,
                "to"        => $toLatitude . "," . $toLongitude,
                'unit'      => $unit,
                'routeType' => $optimizeBy
            );

        $requestData = http_build_query($requestData) . $avoidTypes;

        return $requestData;
    }

    /**
     * Get Geocode Response array from mapquest
     *
     * @param $requestData contains origin and destination params and
     *  route options
     * @return curl execution result
     *
     */
    protected function getRouteResponse($requestData)
    {
        $path = "directions/v2/route?key=". $this->key;

        $urlQuery = parse_url($path, \PHP_URL_QUERY);
        if ($urlQuery) {
            $path .= "&" . $requestData;
        }
        else {
            $path .= "?" . $requestData;
        }

        $curl = $this->createMapServiceRequest($path);
        $response = curl_exec($curl);

        if (! $response) {
            $errorCode = curl_errno($curl);
            throw new \Exception("Mapquest: failed on http request error {$errorCode}.");
        }

        return $response;
    }

    /**
     * Build Address array From MapQuest Result
     *
     * @param array $result response result from mapquest
     * @return array address array
     *
     */
    protected function normalizeGeocodeResult($result)
    {
        $country     = Arr::mustGet("adminArea1", $result);
        $postalCode  = Arr::mustGet("postalCode", $result);

        if ($country == "US") {
            $arr = explode("-", $postalCode);
            if (count($arr) > 0) {
                $postalCode = $arr[0];
            }
        }

        return array(
            "qualityCode" => Arr::get("geocodeQualityCode", $result),
            "street"      => Arr::mustGet("street", $result),
            "city"        => Arr::mustGet("adminArea5", $result),
            "district"    => Arr::mustGet("adminArea4", $result),
            "locality"    => null,
            "subdivision" => Arr::mustGet("adminArea3", $result),
            "postalCode"  => $postalCode,
            "country"     => $country,
            "mapUrl"      => Arr::get("mapUrl", $result),
            "lat"         => Arr::findByKeyChain($result, "latLng.lat"),
            "lng"         => Arr::findByKeyChain($result, "latLng.lng")
        );
    }

    /**
     * Build route info array from mapquest result
     *
     * @param array $result response result from route service
     * @return array route array
     *
     */
    protected function normalizeRouteResult($result)
    {
        $from = Arr::findByKeyChain($result, "locations.0");
        if (empty($from)) {
            throw new \Exception("Mapquest: route result is missing origin.");
        }

        $to = Arr::findByKeyChain($result, "locations.1");
        if (empty($to)) {
            throw new \Exception("Mapquest: route result is missing destination");
        }

        $routeResult = array(
            "distance" => Arr::get("distance", $result),
            "time" => Arr::get("time", $result),
            "from" => $this->normalizeGeocodeResult($from),
            "to" => $this->normalizeGeocodeResult($to)
        );

        $maneuvers = Arr::findByKeyChain($result, "legs.0.maneuvers");
        if (empty($maneuvers)) {
            throw new \Exception("Mapquest: route result is missing maneuvers.");
        }

        foreach ($maneuvers as $maneuver) {
            $routeResult["directions"][] = array(
                "narrative" => Arr::get("narrative", $maneuver),
                "iconUrl"   => Arr::get("iconUrl", $maneuver),
                "distance"  => Arr::get("distance", $maneuver),
                "time"      => Arr::get("time", $maneuver),
                "mapUrl"    => Arr::get("mapUrl", $maneuver),
                "startLat"  => Arr::get("startPoint.lat", $maneuver),
                "startLng"  => Arr::get("startPoint.lng", $maneuver)
                );
        }

        return $routeResult;
    }

    /**
     * Returns avoid types string for request url
     * @param array $options options for routing service
     * @return string
     */
    protected function convertRouteAvoidOptions($options=array())
    {
        // keys coming from 'map.route_avoid_types'
        $routeAvoidTypesList = array(
            MapProviderManager::ROUTE_AVOID_LIMITED_ACCESS      => 'Limited Access',
            MapProviderManager::ROUTE_AVOID_TOLL_ROAD           => 'Toll road',
            MapProviderManager::ROUTE_AVOID_FERRY               => 'Ferry',
            MapProviderManager::ROUTE_AVOID_UNPAVED             => 'Unpaved',
            MapProviderManager::ROUTE_AVOID_SEASONAL_CLOSURE    => 'Approximate Seasonal Closure',
            MapProviderManager::ROUTE_AVOID_BORDER_CROSSING     => 'Country border crossing'
        );

        $routeAvoidTypes = '';

        foreach ($options as $avoidType) {
            if (isset($routeAvoidTypesList[$avoidType])) {
                $routeAvoidTypes .= '&avoids=' . urlencode($routeAvoidTypesList[$avoidType]);
            }
            else {
                throw new \Exception("Please check avoid types configuration. Undefined avoid contant {$avoidType}.");
            }
        }

        return $routeAvoidTypes;
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
            $errorMsg = "Mapquest: response is not valid JSON with error {$jsonDecodeError}";
            return false;
        }

        $statusCode = Arr::findByKeyChain(
            $decodedResponse,
            "info.statuscode");
        $this->logger->debug("Mapquest: response status code is {$statusCode}.");

        if ($statusCode != 0) {
            $message = Arr::findByKeyChain(
                $decodedResponse,
                "info.messages.0");
            $errorMsg = "Mapquest request failed because of :{$message}";
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
    protected function createMapServiceRequest($path)
    {
        $requestUrl = $this->url . $path;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // Do not need to verify ssl
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, static::CONNECTION_TIMEOUT);
        curl_setopt($curl, CURLOPT_TIMEOUT, static::REQUEST_TIMEOUT);
        curl_setopt($curl, CURLOPT_URL, $requestUrl);

        return $curl;
    }
}