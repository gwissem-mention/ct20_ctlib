<?php

namespace CTLib\MapService;

interface Router
{
    /**
    * Get directions from one geo point to another
         *
    * @param float $fromLatitude latitude of origin
    * @param float $fromLongitude longitude of origin
    * @param float $toLatitude latitude of destination
    * @param float $toLongitude longitude of destination
    * @param array $options options for routing service
    * @return array array(
    *                  distance => ...,
    *                  time => ...,
    *                  from => array follows the result format of reverse goecode
    *                  to => array follows the result format of reverse geocode
    *                  directions => array(
    *                      narrative => 
    *                      iconUrl => 
    *                      distance => 
    *                      time => 
    *                      mapUrl =>
    *                      startLat =>
    *                      startLng =>
         *
         */
    public function route($fromLatitude, $fromLongitude, 
        $toLatitude, $toLongitude, $optimizeBy, array $options, $distanceUnit);
    
    /**
     * Calculates estimated time and distance for route between two points.
     *
     * @param float $fromLatitude
     * @param float $fromLongitude
     * @param float $toLatitude
     * @param float $toLongitude
     * @param array $options        Map service-specific options.
     * @param string $country       If null, will use site-configured country.
     *
     * @return array                array($time, $distance)
     *                              $time in seconds
     *                              $distance in country-specific unit.
     */
    public function routeTimeAndDistance($fromLatitude, $fromLongitude,
        $toLatitude, $toLongitude, $optimizeBy, array $options = array(), $distanceUnit);
}