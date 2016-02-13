<?php

namespace CTLib\MapService;

interface Geocoder
{
    /**
    * Geocode address
    *
    * @param array $address
    * @param array $allowedQualityCodes
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
    public function geocode($address, $allowedQualityCodes);
}