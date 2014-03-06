<?php

namespace CTLib\MapService;

interface MapProviderInterface
{
    /**
     * Get Javascript API Library Url
     *
     * @param string $country country code
     * @return string javascript url
     *
     */
    public function getJavascriptApiUrl($country = null);

    /**
     * Get Javascript MapPlugin Name
     *
     * @param string $country country code
     * @return string
     *
     */
    public function getJavascriptMapPlugin($country = null);
    
    /**
     * Geocode address
     *
     * @param string $street street
     * @param string $city city
     * @param string $subdivision subdivision province or state
     * @param string $postalCode postal code
     * @param string $country country
     * @return array array(
     *                  qualityCode => ...,
     *                  street => ...,
     *                  city => ...,
     *                  district => ...,
     *                  locality => ...,
     *                  subdivision => ...,
     *                  country => ...,
     *                  postalCode => ...,
     *                  mapUrl => ...,
     *                  lat => ...,
     *                  lng => ...
     *              )
     *
     */
    public function geocode($address, $country = null);

    /**
     * Batch geocode addresses
     *
     * @param array $streets all streets in an array
     * @return array array of returned geocodes, each one follows 
     * the format of geocode function
     *
     */    
    public function geocodeBatch(array $addresses, $country = null);

    /**
     * Reverse geocode latitude and longitude
     *
     * @param float $latitude latitude
     * @param float $longitude longitude
     * @return array returned array follows those in geocode function
     *
     */   
    public function reverseGeocode($latitude, $longitude, $country = null);

    /**
     * Batch reverse geocode latitude and longitude
     *
     * @param array $latLngs array of array(lat, lng)
     * @return array array of returned reverse geocode, each one 
     * follows the format of reverseGeocode function
     *
     */
    public function reverseGeocodeBatch(array $latLngs, $country = null);

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
        $toLatitude, $toLongitude, $optimizeBy, array $options, $country = null);

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
        $toLatitude, $toLongitude, $optimizeBy, array $options = array(), $country = null);

    /**
     * Get Allowed Quality codes for address
     *
     * @param string $country country
     * @return array array of allowed quality codes
     *
     */
    public function getAllowedQualityCodes($country = null); 
}