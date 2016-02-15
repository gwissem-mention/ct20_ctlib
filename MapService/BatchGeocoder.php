<?php

namespace CTLib\MapService;

interface BatchGeocoder
{
    /**
     * Batch geocode addresses
     *
     * @param array $addresses all addresses in an array
     * @param array $tokens address components in an array
     * @param array $allowedQualityCodes allowed quality codes for validation
     * @return array array of returned geocodes, each one follows 
     * the format of geocode function
     *
     */
    public function geocodeBatch(array $addresses, $allowedQualityCodes, $batchSize);
}