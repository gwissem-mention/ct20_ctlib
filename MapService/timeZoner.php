<?php

namespace CTLib\MapService;

interface timeZoner
{
    /**
     * Get time zone by latitude and longitude
     *
     * @param float $latitude latitude
     * @param float $longitude longitude
     * @return string timeZoneId
     */
    public function timeZone($latitude, $longitude);
}