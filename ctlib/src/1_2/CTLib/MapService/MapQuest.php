<?php

namespace CTLib\MapService;

use CTLib\Util\Arr,
    CTLib\Util\CTCurl;


class MapQuest extends MapProviderAbstract
{
    const BATCH_GEOCODE_LIMIT = 100;

    /**
     * {@inheritdoc}
     */
    public function getJavascriptApiUrl($country = null)
    {
        return "https://www.mapquestapi.com/sdk/js/v7.0.s/mqa.toolkit.js?key=Gmjtd%7Clu6zn1ua2d%2C7s%3Do5-l07g0";
    }

    /**
     * {@inheritdoc}
     */
    public function getJavascriptMapPlugin($country = null)
    {
        return "mapquest.maps.plugin.js";
    }
    
    /**
     * {@inheritdoc}
     */
    protected function geocodeBuildRequest($request, $address, $country = null)
    {
        $request->url = "https://www.mapquestapi.com/geocoding/v1/address?key=Gmjtd%7Clu6zn1ua2d%2C7s%3Do5-l07g0";
        if (is_string($address)) {
            $request->data = array("location" => $address);
        }
        else {
            $request->data = $this->buildAddressRequestData($address);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function geocodeProcessResult($result)
    {
        $decodedResult = json_decode($result, true);
        return $this->buildAddressFromMapQuestResult($decodedResult, "results.0.locations.0");
    }

    /**
     * {@inheritdoc}
     */
    protected function geocodeBatchBuildRequest($request, $addresses, $country = null)
    {
        $request->IsBatch = true;
        $request->BatchLimit = static::BATCH_GEOCODE_LIMIT;
        $request->Url = "https://www.mapquestapi.com/geocoding/v1/batch?key=Gmjtd%7Clu6zn1ua2d%2C7s%3Do5-l07g0";
        $request->Method = CTCurl::REQUEST_POST;

        $data = array();
        foreach ($addresses as $address) {
            if (is_string($address)) {
                $data[] = array("location" => $address);
            }
            else {
                $data[] = array("location" => json_encode($this->buildAddressRequestData($address)));
            }
        }
        $request->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function geocodeBatchProcessResult($result)
    {
        if (empty($result) || !is_array($result)) {
            throw new \Exception("result is not valid");
        }

        $combinedResult = array();
        foreach($result as $batch) {
            $decodedResult = json_decode($batch, true);
            if (!$decodedResult) {
                throw new \Exception("result is invalid");
            }

            $batchResults = Arr::mustGet("results", $decodedResult);
            foreach ($batchResults as $row) {
                $combinedResult[] = $this->buildAddressFromMapQuestResult($row, "locations.0");
            }
        }
        return $combinedResult;
    }

    /**
     * {@inheritdoc}
     */
    protected function reverseGeocodeBuildRequest($request, $latitude, $longitude, $country = null)
    {
        $request->url = "https://www.mapquestapi.com/geocoding/v1/reverse?key=Gmjtd%7Clu6zn1ua2d%2C7s%3Do5-l07g0";
        $request->data = array(
            "lat" => $latitude,
            "lng" => $longitude
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function reverseGeocodeProcessResult($result)
    {
        $decodedResult = json_decode($result, true);
        $address = $this->buildAddressFromMapQuestResult($decodedResult, "results.0.locations.0");

        // if country is canada, do another geocode to correct 
        // postalCode for 3-character postal code
        if ($address["country"] == "CA") {
            $address = $this->geocode($address, $address["country"]);
        }
        
        return $address;
    }

    /**
     * {@inheritdoc}
     */
    protected function reverseGeocodeBatchBuildRequest($request, array $latLngs, $country = null)
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function reverseGeocodeBatchProcessResult($result)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function reverseGeocodeBatch(array $latLngs, $country = null)
    {
        $result = array();
        foreach ($latLngs as $latLng) {
            $result[] = $this->reverseGeocode($latLng[0], $latLng[1], $country);
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function routeBuildRequest($request, $fromLatitude, $fromLongitude, $toLatitude, $toLongitude, $options, $country = null)
    {
        $request->url = "https://www.mapquestapi.com/directions/v1/alternateroutes?key=Gmjtd%7Clu6zn1ua2d%2C7s%3Do5-l07g0";
        $request->data = 
            array_merge(
                array(
                    "from" => $fromLatitude . "," . $fromLongitude,
                    "to" => $toLatitude . "," . $toLongitude,
                    "maxRoutes" => 3
                ),
                $options
            );
        $request->method = CTCurl::REQUEST_GET;
    }
    
    /**
     * Returns shortest route information contained within raw MapQuest route
     * response.
     *
     * @param string $result    JSON MapQuest route response.
     * @param string $metric    Shortest will be determined using this metric:
     *                          either 'distance' or 'time'.
     * @return array
     */
    protected function extractShortestRoute($result, $metric)
    {
        if (! in_array($metric, array('time', 'distance'))) {
            throw new \Exception('Invalid $metric');
        }

        $decodedResult = json_decode($result, true);

        if (! $decodedResult) {
            throw new \Exception("result is invalid");
        }

        $route = Arr::get("route", $decodedResult);
        if (! $route) {
            throw new \Exception("result is invalid");
        }

        $alternateRoutes = Arr::extract('alternateRoutes', $route);

        if (! $alternateRoutes) {
            // No need to compute shortest route when there's only one
            // available.
            return $route;
        }

        // Concatenate primary route with alternates, and return shortest of the
        // set.
        $routes = array_map(
            function($r) { return $r['route']; },
            $alternateRoutes
        );
        array_unshift($routes, $route);

        usort($routes, function($r1, $r2) use ($metric) {
            if ($r1[$metric] == $r2[$metric]) { return 0; }
            return $r1[$metric] < $r2[$metric] ? -1 : 1;
        });
        return $routes[0];
    }

    /**
     * {@inheritdoc}
     */
    protected function routeProcessResult($result)
    {
        $route = $this->extractShortestRoute($result, 'distance');

        $routeResult = array(
            "distance" => Arr::get("distance", $route),
            "time" => Arr::get("time", $route),
            "from" => $this->buildAddressFromMapQuestResult($route, "locations.0"),
            "to" => $this->buildAddressFromMapQuestResult($route, "locations.1"),
            "directions" => array()
        );

        $maneuvers = Arr::findByKeyChain($route, "legs.0.maneuvers");
        if (!$maneuvers) {
            throw new \Exception("result is invalid");
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
     * @inherit
     */
    protected function routeTimeAndDistanceProcessResult($result)
    {
        $route = $this->extractShortestRoute($result, 'distance');
        return array(Arr::get("time", $route), Arr::get("distance", $route));
    }

    /**
     * Build Address array From MapQuest Result
     *
     * @param array $result response result from mapquest
     * @param string $keyChain key change to get from result
     * @return array address array
     *
     */
    protected function buildAddressFromMapQuestResult($result, $keyChain)
    {
        $mapquestResult = Arr::findByKeyChain($result, $keyChain);
        if (empty($mapquestResult)) {
            throw new \Exception("Array is invalid");
        }

        $country     = Arr::mustGet("adminArea1", $mapquestResult);
        $postalCode  = Arr::mustGet("postalCode", $mapquestResult);
        
        if ($country == "US") {
            $arr = explode("-", $postalCode);
            if (count($arr) > 0) {
                $postalCode = $arr[0];
            }
        }
        
        return array(
            "qualityCode" => Arr::mustGet("geocodeQualityCode", $mapquestResult),
            "street"      => Arr::mustGet("street", $mapquestResult),
            "city"        => Arr::mustGet("adminArea5", $mapquestResult),
            "district"    => Arr::mustGet("adminArea4", $mapquestResult),
            "locality"    => null,
            "subdivision" => Arr::mustGet("adminArea3", $mapquestResult),
            "postalCode"  => $postalCode,
            "country"     => $country,
            "mapUrl"      => Arr::get("mapUrl", $mapquestResult),
            "lat"         => Arr::findByKeyChain($mapquestResult, "latLng.lat"),
            "lng"         => Arr::findByKeyChain($mapquestResult, "latLng.lng")
        );
    }

    /**
     * Build Address Request array sending to mapquest
     *
     * @param array $address that contains This is a description
     * @return mixed This is the return value description
     *
     */
    protected function buildAddressRequestData($address)
    {
        $params = array(
            "street"     => Arr::mustGet("street", $address),
            "adminArea5" => Arr::mustGet("city", $address),
            "adminArea3" => Arr::get("subdivision", $address),
            "postalCode" => Arr::mustGet("postalCode", $address),
        );
        $country = Arr::get("country", $address);
        if ($country) { $params["adminArea1"] = $country; }
        return $params;
    }
}