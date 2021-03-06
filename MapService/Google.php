<?php

namespace CTLib\MapService;

use CTLib\Util\Arr;

class Google implements Geocoder, ReverseGeocoder, Router, TimeZoner
{
    const CONNECTION_TIMEOUT = 5;
    const REQUEST_TIMEOUT = 10;

    const MILES         = 'imperial';
    const KILOMETERS    = 'metric';

    const SHORTEST_DISTANCE = 'distance';
    const SHORTEST_TIME     = 'duration';

    /**
     * @var string
     */
    protected $url;

    public function __construct($javaScriptUrl, $javaScriptKey, $webServiceUrl, $webServiceKey, $logger)
    {
        $this->logger = $logger;
        
        $this->javaScriptUrl    = $javaScriptUrl;
        $this->javaScriptKey    = $javaScriptKey;
        
        $this->webServiceUrl    = $webServiceUrl;
        $this->webServiceKey    = $webServiceKey;
    }

    /**
     * Return mapquest javascript api url
     */
    public function getJavascriptApiUrl()
    {
        return $this->javaScriptUrl . "js?key=" . $this->javaScriptKey;
    }
   
    /**
     * Return mapquest javascript plugin
     */
    public function getJavascriptMapPlugin()
    {
        return "google.maps.plugin.js";
    }

    /**
     * Implements method in Geocoder
     *
     * @param array $address
     * @param array $allowedQualityCodes
     * @param array $componentOrderedWhitelist
     * @return array|mixed
     * @throws \Exception
     */
    public function geocode(array $address, array $allowedQualityCodes, array $componentOrderedWhitelist)
    {
        $requestData = $this->buildGeocodeRequestData($address, $componentOrderedWhitelist);
        $response = $this->getGeocodeResponse($requestData);
        $this->logger->debug("Google: geocode response is {$response}.");

        $decodedResult = json_decode($response, true);
        if (! $this->isValidResponse($decodedResult, $errorMsg)) {
            throw new \Exception("Google: invalid geocode response with error {$errorMsg}");
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
     * Get location time zone by latitude and longitude
     *
     * @param float $latitude
     * @param float $longitude
     * @return mixed
     * @throws \Exception
     */
    public function getTimeZone($latitude, $longitude)
    {
        $response = $this->getTimeZoneResponse($latitude, $longitude);
        $this->logger->debug("Google: getting time zone response is {$response}.");

        if (!$response) {
            throw new \Exception("Google: time zone result is invalid");
        }

        $timeZoneResult = json_decode($response, true);
        if (!$this->isValidResponse($timeZoneResult, $errorMsg)) {
            throw new \Exception("Google: invalid time zone response with error {$errorMsg}");
        }

        if (!isset($timeZoneResult['timeZoneId'])) {
            throw new \Exception("Google: time zone id is not available in the result.");
        }
        return $timeZoneResult['timeZoneId'];
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
            throw new \Exception("Google: invalid reverse geocode response with error {$errorMsg}");
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
     * Implements method in Router
     *
     */
    public function route(
        $fromLatitude,
        $fromLongitude,
        $toLatitude,
        $toLongitude,
        $optimizeBy,
        array $options,
        $distanceUnit)
    {
        $requestData = $this->buildRouteRequestData(
            $fromLatitude,
            $fromLongitude,
            $toLatitude,
            $toLongitude,
            $options,
            $distanceUnit
        );

        $response = $this->getRouteResponse($requestData);

        $decodedResult = json_decode($response, true);
        if (! $this->isValidResponse($decodedResult, $errorMsg)) {
            throw new \Exception("Google invalid route response with error {$errorMsg}");
        }

        $route = $this->extractShortestRoute($decodedResult, $optimizeBy);
        $routeResult = $this->normalizeRouteResult($route, $distanceUnit);

        return $routeResult;
    }

    /**
     * Build route info array from google result
     *
     * @param array $result response result from route service
     * @return array route array
     *
     */
    protected function normalizeRouteResult($result, $distanceUnit)
    {
        $routeResult = array(
            "distance"  => $this
                    ->convertDistanceValue(
                        $result['legs'][0]['distance']['value'], $distanceUnit),
            "time"      => $result['legs'][0]['duration']['value'],
            "from"      => null,
            "to"        => null,
            "polyline"  => $result['overview_polyline']['points']
        );

        $maneuvers = Arr::findByKeyChain($result, "legs.0.steps");
        if (empty($maneuvers)) {
            throw new \Exception("Google: route result is missing maneuvers.");
        }

        foreach ($maneuvers as $maneuver) {
            $startPoint = Arr::get('start_location', $maneuver);

            $routeResult["directions"][] = array(
                "narrative" => null,
                "iconUrl"   => null,
                "distance"  => $this
                                ->convertDistanceValue(
                                $maneuver['distance']['value'], $distanceUnit),
                "time"      => $maneuver['duration']['value'],
                "mapUrl"    => null,
                "startLat"  => Arr::get("lat", $startPoint),
                "startLng"  => Arr::get("lng", $startPoint)
            );
        }

        return $routeResult;
    }

    /**
     * Implements method in Router
     *
     */
    public function routeTimeAndDistance(
        $fromLatitude,
        $fromLongitude,
        $toLatitude,
        $toLongitude,
        $optimizeBy,
        array $options,
        $distanceUnit)
    {
        $requestData = $this->buildRouteRequestData(
            $fromLatitude,
            $fromLongitude,
            $toLatitude,
            $toLongitude,
            $options,
            $distanceUnit
        );

        $response = $this->getRouteResponse($requestData);

        $decodedResult = json_decode($response, true);

        if (! $this->isValidResponse($decodedResult, $errorMsg)) {
            throw new \Exception("Google invalid route response with error {$errorMsg}");
        }

        $route = $this->extractShortestRoute($decodedResult, $optimizeBy);

        $distance = $this
            ->convertDistanceValue(
                $route['legs'][0]['distance']['value'], $distanceUnit);

        $time = $route['legs'][0]['duration']['value'];

        return array($time, $distance);
    }

    /**
     * Returns shortest route information contained within raw Google route
     * response.
     *
     * @param string $results         JSON MapQuest route response.
     * @param string $optimizedBy    Shortest will be determined using this metric:
     *                          either 'shortest' or 'fastest'.
     * @return array
     */
    protected function extractShortestRoute($results, $optimizedBy)
    {
        switch ($optimizedBy) {
            case 'shortest':
                $metric = self::SHORTEST_DISTANCE;
                break;
            default:
                $metric = self::SHORTEST_TIME;
        }

        $routes = Arr::get("routes", $results);

        if (empty($routes)) {
            throw new \Exception("Google: route result is invalid");
        }

        usort($routes, function($r1, $r2) use ($metric) {
            if ($r1['legs'][0][$metric]['value'] == $r2['legs'][0][$metric]['value'])
            { return 0; }
            return
                $r1['legs'][0][$metric]['value'] < $r2['legs'][0][$metric]['value']
                    ? -1 : 1;
        });

        return $routes[0];
    }

    /**
     * Convert distance value (meters) into kilometers or miles
     *
     * @param $distanceValue integer
     * @param $distanceUnit string
     *
     * @return $distance
     */
    protected function convertDistanceValue($distanceValue, $distanceUnit)
    {
        switch ($distanceUnit) {
            case 'kilometer':
                $distance = $distanceValue / 1000;
                break;
            default:
                $distance = $distanceValue / 1609.344;
        }

        return $distance;
    }

    /**
     * Build request data array sending to google
     *
     * @param $fromLatitude origin latitude
     * @param $fromLongitude origin longitude
     * @param $toLatitude destination latitude
     * @param $toLongitude destination longitude
     * @param $optimizeBy route request type
     * @param $options route avoid options
     * @param $distanceUnit distance unit by country
     * @return formatted request data string for google
     *
     */
    protected function buildRouteRequestData(
        $fromLatitude,
        $fromLongitude,
        $toLatitude,
        $toLongitude,
        $options,
        $distanceUnit)
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
                'origin'        => $fromLatitude . "," . $fromLongitude,
                'destination'   => $toLatitude . "," . $toLongitude,
                'units'         => $unit
            );

        $requestData = http_build_query($requestData) . $avoidTypes;

        return $requestData;
    }

    /**
     * Get Route Response array from google
     *
     * @param $requestData contains origin and destination params and
     *  route options
     * @return curl execution result
     *
     */
    protected function getRouteResponse($requestData)
    {
        $curl = $this->createMapServiceRequest();
        $requestUrl = $this->webServiceUrl . "directions/json?". $requestData.
            "&alternatives=true&&key=". $this->webServiceKey;

        curl_setopt($curl, CURLOPT_URL, $requestUrl);

        $response = curl_exec($curl);

        if (! $response) {
            $errorCode = curl_errno($curl);
            throw new \Exception("Google: failed on http request error. {$errorCode}");
        }

        return curl_exec($curl);
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
            MapProviderManager::ROUTE_AVOID_TOLL_ROAD           => 'tolls',
            MapProviderManager::ROUTE_AVOID_HIGHWAY             => 'highways',
            MapProviderManager::ROUTE_AVOID_FERRY               => 'ferries',
            MapProviderManager::ROUTE_AVOID_LIMITED_ACCESS      => '',
            MapProviderManager::ROUTE_AVOID_UNPAVED             => '',
            MapProviderManager::ROUTE_AVOID_SEASONAL_CLOSURE    => '',
            MapProviderManager::ROUTE_AVOID_BORDER_CROSSING     => ''
        );

        $routeAvoidTypes = '';

        foreach ($options as $avoidType) {
            if (isset($routeAvoidTypesList[$avoidType])) {
                $routeAvoidTypes .= '&avoid=' . urlencode($routeAvoidTypesList[$avoidType]);
            }
            else {
                throw new \Exception("Please check avoid types configuration. Undefined avoid constant {$avoidType}.");
            }
        }

        return $routeAvoidTypes;
    }

    /**
     * Build Address Request array sending to google
     *
     * This method does 3 things
     * 1) Order the array $address components supplied from $componentOrderedWhitelist defined in config.yml
     * 2) Only allow the whitelisting of $address components supplied from $componentOrderedWhitelist
     * 3) Return a string that is a built up order of $componentOrderedWhitelist that is URL encoded.
     *
     * @param array $address
     * @param array $componentOrderedWhitelist
     * @return string
     */
    function buildGeocodeRequestData($address, $componentOrderedWhitelist) {

        $urlComponentArray = [];

        foreach($componentOrderedWhitelist as $whiteListComponentKey)
        {
            $urlComponentArray[] = Arr::get($whiteListComponentKey, $address);
        }

        return urlencode(implode(" ", array_filter($urlComponentArray)));

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

        $requestUrl = $this->webServiceUrl . "geocode/json?address=". $requestData .
            "&sensor=false&key=". $this->webServiceKey;

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
        $requestUrl = $this->webServiceUrl . "geocode/json?latlng=". $requestData .
            "&sensor=false&key=". $this->webServiceKey;

        curl_setopt($curl, CURLOPT_URL, $requestUrl);

        $response = curl_exec($curl);

        if (! $response) {
            $errorCode = curl_errno($curl);
            throw new \Exception("Google: failed on http request error. {$errorCode}");
        }

        return curl_exec($curl);
    }

    /**
     * Send curl request to time zone api and get response
     *
     * @param float $latitude
     * @param float $longitude
     * @return mixed
     * @throws \Exception
     */
    protected function getTimeZoneResponse($latitude, $longitude)
    {
        $curl = $this->createMapServiceRequest();
        $requestData = $latitude . "," . $longitude;
        $requestUrl = $this->webServiceUrl . "timezone/json?location=". $requestData .
            "&timestamp=" . time() . "&key=". $this->webServiceKey;

        curl_setopt($curl, CURLOPT_URL, $requestUrl);

        $response = curl_exec($curl);

        if (! $response) {
            $errorCode = curl_errno($curl);
            throw new \Exception("Google: getting time zone request failed on http request error. {$errorCode}");
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
                $addressComponents['subdivision'] = $component['short_name'];
            }
            if (in_array("administrative_area_level_2", $component['types'])) {
                $addressComponents['district'] = $component['long_name'];
            }
            if (in_array("administrative_area_level_3", $component['types'])) {
                $addressComponents['locality'] = $component['long_name'];
            }
            if (in_array("country", $component['types'])) {
                $addressComponents['country'] = $component['short_name'];
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
        $this->logger->debug("Google: response status is {$status}.");

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