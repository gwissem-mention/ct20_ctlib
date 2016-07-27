<?php

namespace CTLib\MapService;

interface TimeZoner
{
    /**
     * Get time zone by latitude and longitude
     *
     * @param float $latitude latitude
     * @param float $longitude longitude
     * @return string timeZoneId
     */
    public function getTimeZone($latitude, $longitude);
}