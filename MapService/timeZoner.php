<?php

namespace CTLib\MapService;

interface timeZoner
{
    /**
     * Reverse geocode latitude and longitude
     *
     * @param float $latitude latitude
     * @param float $longitude longitude
     * @return array array(
     *                  dstOffset => ...,
     *                  rawOffset => ...,
     *                  timeZoneId => ...,
     *                  timeZoneName => ...
     *              )
     *
     */
    public function timeZone($latitude, $longitude);
}