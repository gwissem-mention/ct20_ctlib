<?php

namespace CTLib\MapService;

interface Geocoder
{
    /**
    * Geocode address
    *
    * @param array $address
    * @param array $allowedQualityCodes
    * @param array $componentOrderedWhitelist
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
    public function geocode(array $address, array $allowedQualityCodes, array $componentOrderedWhitelist);
}