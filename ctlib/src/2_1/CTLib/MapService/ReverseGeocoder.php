<?php

namespace CTLib\MapService;

interface ReverseGeocoder
{
    /**
     * Reverse geocode latitude and longitude
     *
     * @param float $latitude latitude
     * @param float $longitude longitude
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
    public function reverseGeocode($latitude, $longitude);
}