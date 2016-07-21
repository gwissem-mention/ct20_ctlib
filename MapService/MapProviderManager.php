<?php
namespace CTLib\MapService;

use Symfony\Component\Yaml\Yaml,
    Symfony\Component\HttpKernel\Config\FileLocator,
    CTLib\Util\Arr,
    CTLib\Helper\LocalizationHelper;

class MapProviderManager
{
    /**
     * @const constants for route optimized types configuration
     */
    const OPTIMIZE_BY_TIME              = 'fastest';
    const OPTIMIZE_BY_DISTANCE          = 'shortest';

    /**
     * @const constants for route avoid options configuration
     */
    const ROUTE_AVOID_LIMITED_ACCESS    = 'LimitedAccess';
    const ROUTE_AVOID_TOLL_ROAD         = 'Toll';
    const ROUTE_AVOID_FERRY             = 'Ferry';
    const ROUTE_AVOID_UNPAVED           = 'Unpaved';
    const ROUTE_AVOID_SEASONAL_CLOSURE  = 'SeasonalClosure';
    const ROUTE_AVOID_BORDER_CROSSING   = 'CountryBorder';
    const ROUTE_AVOID_HIGHWAY           = 'Highway';

    /**
     * @var string
     */
    protected $defaultCountry;

    /**
     * @var array
     */
    protected $providers;

    /**
     * @var array
     */
    protected $geocoders;

    /**
     * @var array
     */
    protected $reverseGeocoders;

    /**
     * @var array
     */
    protected $routers;

    /**
     * @var array
     */
    protected $timeZoners;

    /**
     * @var array
     */
    protected $apis;

    /**
     * @param string $defaultCountry
     * @param Logger $logger
     * @param LocalizerHelper $localizer
     */
    public function __construct($defaultCountry, $logger, $localizer)
    {
        $this->defaultCountry   = $defaultCountry;
        $this->logger           = $logger;
        $this->localizer        = $localizer;
        $this->providers        = array();
        $this->geocoders        = array();
        $this->reverseGeocoders = array();
        $this->routers          = array();
        $this->timeZoners       = array();
        $this->apis             = array();
    }

    /** Register map service provider
     * @param string $providerId provider name
     * @param string $class provider class
     * @param string $javascript_url javascript provider url
     * @param string $javascript_key provider url
     * @param string $webservice_url provider url
     * @param string $webservice_key provider url
     */
    public function registerProvider($providerId, $class, $javaScriptUrl, $javaScriptKey, $webServiceUrl, $webServiceKey)
    {
        $provider = new $class($javaScriptUrl, $javaScriptKey, $webServiceUrl, $webServiceKey, $this->logger);
        $this->providers[$providerId] = $provider;
    }

    /** Register map service providers
     *
     * return array providers
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /** Register map service geocoder
     * @param string $country
     * @param string $providerId provider name
     * @param string $tokens tokens to filter address components
     * @param string $allowedQualityCodes quality codes set for
     * address validation
     * @param string $batchSize batch limit for map service supports
     * batch geocode
     */
    public function registerGeocoder(
        $country,
        $providerId,
        $tokens,
        $allowedQualityCodes,
        $batchSize = null)
    {
        if (!isset($this->providers[$providerId])) {
            throw new \Exception("Can not find provider with provider id: {$providerId}");
        }

        $this->geocoders[$country][] = array(
            'providerId'          => $providerId,
            'tokens'              => $tokens,
            'allowedQualityCodes' => $allowedQualityCodes,
            'batchSize'           => $batchSize
        );
    }

    /** Register map service geocoders
     *
     * return array geocoders
     */
    public function getGeocoders()
    {
        return $this->geocoders;
    }

    /** Register map service reverse geocoder
     * @param string $country
     * @param string $providerId provider name
     */
    public function registerReverseGeocoder($country, $providerId)
    {
        if (!isset($this->providers[$providerId])) {
            throw new \Exception("Can not find provider with provider id: {$providerId}");
        }

        $this->reverseGeocoders[$country][] = array(
            'providerId' => $providerId
        );
    }

    /** Register map service router
     * @param string $country
     * @param string $providerId provider name
     */
    public function registerRouter($country, $providerId)
    {
        if (!isset($this->providers[$providerId])) {
            throw new \Exception("Can not find provider with provider id: {$providerId}");
        }

        $this->routers[$country] = array(
            'providerId' => $providerId
        );
    }

    /**
     * Register map service time zone provider
     *
     * @param string $country
     * @param string $providerId
     * @throws \Exception
     */
    public function registerTimeZoner($country, $providerId)
    {
        if (!isset($this->providers[$providerId])) {
            throw new \Exception("Can not find provider with provider id: {$providerId}");
        }

        $this->timeZoners[$country] = [
            'providerId' => $providerId
        ];
    }

    /** Register map service router
     * @param string $country
     * @param string $providerId provider name
     */
    public function registerAPI($country, $providerId)
    {
        if (!isset($this->providers[$providerId])) {
            throw new \Exception("Can not find provider with provider id: {$providerId}");
        }

        $this->apis[$country] = array(
            'providerId' => $providerId
        );
    }

    /**
     * Geocode address
     * @param array $address
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
     *                  lng => ...,
     *                  isValidated => ...
     *              )
     *
     */
    public function geocode($address)
    {
        $country = Arr::get('countryCode', $address, $this->defaultCountry);

        if (! isset($this->geocoders[$country])) {
            throw new \Exception("Can not find geocode provider for country {$country}");
        }

        $geocodeResult = array();
        foreach ($this->geocoders[$country] as $priority => $geocoder) {
            $geocodeProvider = $this->providers[$geocoder['providerId']];
            $this->logger->debug("Geocode provider is {$geocoder['providerId']} with priority {$priority}.");

            $result = array();

            //filter address components by tokens configuration
            $filteredAddress = $this
                ->filterAddressByTokens(
                    $address,
                    $geocoder['tokens']);

            try {
                $result = $geocodeProvider
                    ->geocode(
                        $filteredAddress,
                        $geocoder['allowedQualityCodes']);

                if (isset($result['isValidated']) && $result['isValidated']) {
                    return $result;
                }

                //save the first priority geocode result
                if (! $geocodeResult) {
                    $geocodeResult = $result;
                } elseif ($geocodeResult['street'] == '' &&
                    $geocodeResult['city'] == '' &&
                    $geocodeResult['subdivision'] == '') {
                    $geocodeResult = $result;
                }
            } catch (\Exception $e) {
                $this->logger->warn("Geocode provider exception: {$e}.");
            }
        }

        //if all geocoder fails to validate without exception, return the 
        //result from the geocoder with first priority
        return $geocodeResult;
    }

    /**
     * Batch geocode addresses
     *
     * @param array addresses all addresses in an array
     * @return array array of returned geocodes, each one follows
     * the format of geocode function
     *
     */
    public function geocodeBatch(array $addresses)
    {
        if (! $addresses) {
            return array();
        }

        $country = Arr::get('countryCode', $addresses[0], $this->defaultCountry);

        if (! isset($this->geocoders[$country])) {
            throw new \Exception("Can not find geocode provider for country {$country}");
        }

        $batchResults = array();
        foreach ($this->geocoders[$country] as $priority => $geocoder) {
            $geocodeProvider = $this->providers[$geocoder['providerId']];
            $this->logger->debug("Geocode provider is {$geocoder['providerId']} with priority {$priority}.");
            $results = array();

            //filter address components by tokens 
            $filteredAddresses = array_map(
                function ($address) use ($geocoder) {
                    return $this->
                        filterAddressByTokens(
                            $address,
                            $geocoder['tokens']);
                },
                $addresses);

            //check if current map service provider supports batch    
            if ($geocodeProvider instanceof BatchGeocoder) {
                try {
                    $results = $geocodeProvider
                        ->geocodeBatch(
                            $filteredAddresses,
                            $geocoder['allowedQualityCodes'],
                            $geocoder['batchSize']);
                } catch (\Exception $e) {
                    $this->logger->warn("Geocode exception: {$e}.");
                }
            } else {
                foreach ($filteredAddresses as $index => $filteredAddress ) {
                    try {
                        $result = $geocodeProvider
                            ->geocode(
                                $filteredAddress,
                                $geocoder['allowedQualityCodes']);
                        $results[$index] = $result;
                        if ($batchResults) {
                            if ($batchResults[$index]['street'] == '' &&
                                $batchResults[$index]['city'] == '' &&
                                $batchResults[$index]['subdivision'] == '') {
                                $batchResults[$index] = $result;
                            }
                        }
                    } catch (\Exception $e) {
                        $this->logger->warn("Geocode exception: {$e}.");
                    }
                }
            }

            //get validated addresses from current batch geocode results
            $validatedAddresses = array_filter(
                $results,
                function ($r) {
                    return $r['isValidated'] == 1;
                });

            //get invalid addresses from current batch geocode results                       
            $invalidAddresses = array_diff_key($results, $validatedAddresses);

            //update final batch results by validated addresses
            //if batch result is null, save first priority results
            //otherwise replace invalid batch results with validated addresses 
            if (! $batchResults) {
                $batchResults = $results;
            } else {
                $batchResults = array_replace($batchResults, $validatedAddresses);
            }

            //validate invalid addresses only each time
            //if there is no invalid addresses, return final batch results
            if ($invalidAddresses) {
                $addresses = array_intersect_key(
                    $addresses,
                    $invalidAddresses);
            } else {
                return $batchResults;
            }
        }

        return $batchResults;
    }

    /**
     * Get time zone of given location
     *
     * @param float $latitude
     * @param float $longitude
     * @param string|null $country
     * @return mixed
     * @throws \Exception
     */
    public function getTimeZone($latitude, $longitude, $country = null)
    {
        if (!$country) {
            $country = $this->defaultCountry;
        }

        $timeZoneProvider = $this->getTimeZoneProvider($country);

        $timeZone = $timeZoneProvider->getTimeZone($latitude, $longitude);

        return $timeZone;
    }

    /**
     * Reverse geocode latitude and longitude
     *
     * @param float $latitude latitude
     * @param float $longitude longitude
     * @return array returned array follows those in geocode function
     *
     */
    public function reverseGeocode($latitude, $longitude, $country=null)
    {
        if ($country === null) {
            $country = $this->defaultCountry;
        }

        if (! isset($this->reverseGeocoders[$country])) {
            throw new \Exception("Can not find geocode provider for country {$country}");
        }

        $reverseGeocodeResult = array();
        foreach ($this->reverseGeocoders[$country] as $priority => $reverseGeocoder) {
            $reverseGeocodeProvider = $this
                ->providers[$reverseGeocoder['providerId']];
            $this->logger->debug("Reverse geocode provider is {$reverseGeocoder['providerId']} with priority {$priority}.");

            $result = array();

            try {
                $result = $reverseGeocodeProvider
                    ->reverseGeocode(
                        $latitude,
                        $longitude,
                        $country
                    );

                if (isset($result['isValidated']) && $result['isValidated']) {
                    return $result;
                }

                //save the first priority reverse geocode result
                if ($reverseGeocodeResult) {
                    $reverseGeocodeResult = $result;
                }
            } catch (\Exception $e) {
                $this->logger->warn("Reverse geocode exception: {$e}.");
            }
        }

        if ($reverseGeocodeResult) {
            $reverseGeocodeResult['isValidated'] = false;
        }

        //if all reverse geocoder fails to validate, return the result from 
        //the reverse geocoder with first priority
        return  $reverseGeocodeResult;
    }

    /**
     * Get directions from one geo point to another
     *
     * @param float $fromLatitude latitude of origin
     * @param float $fromLongitude longitude of origin
     * @param float $toLatitude latitude of destination
     * @param float $toLongitude longitude of destination
     * @param string $optimizeBy route specific type shortest/fastest
     * @param array $options options for routing service
     * @param string $country if null, will use site-configured country
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
    public function route(
        $fromLatitude,
        $fromLongitude,
        $toLatitude,
        $toLongitude,
        $optimizeBy,
        array $options,
        $country=null)
    {
        if (! $country) {
            $country = $this->defaultCountry;
        }

        $this->checkMapServiceConfiguration($optimizeBy, $options);

        $routeProvider = $this->getRouteProvider($country);
        $distanceUnit = $this->localizer->getCountryDistanceUnit($country);

        $result = $routeProvider
            ->route(
                $fromLatitude,
                $fromLongitude,
                $toLatitude,
                $toLongitude,
                $optimizeBy,
                $options,
                $distanceUnit
            );

        return $result;
    }

    /**
     * Calculates estimated time and distance for route between two points.
     *
     * @param float $fromLatitude
     * @param float $fromLongitude
     * @param float $toLatitude
     * @param float $toLongitude
     * @param string $optimizeBy route specific type shortest/fastest
     * @param array $options map route service-specific options
     * @param string $country if null, will use site-configured country
     *
     * @return array  array($time, $distance)
     *                $time in seconds
     *                $distance in country-specific unit.
     */
    public function routeTimeAndDistance(
        $fromLatitude,
        $fromLongitude,
        $toLatitude,
        $toLongitude,
        $optimizeBy,
        array $options,
        $country=null)
    {
        if (! $country) {
            $country = $this->defaultCountry;
        }

        $this->checkMapServiceConfiguration($optimizeBy, $options);

        $routeProvider = $this->getRouteProvider($country);
        $distanceUnit = $this->localizer->getCountryDistanceUnit($country);

        $result = $routeProvider
            ->routeTimeAndDistance(
                $fromLatitude,
                $fromLongitude,
                $toLatitude,
                $toLongitude,
                $optimizeBy,
                $options,
                $distanceUnit
            );

        return $result;
    }

    /**
     * Check map service configuration
     *
     * @param string $optimizeBy route specific type shortest/fastest
     * @param array $options map route service-specific options
     *
     * throw InvalidArgumentException if configuration is not valid
     */
    public function checkMapServiceConfiguration($optimizeBy, array $options)
    {
        if ($optimizeBy) {
            switch ($optimizeBy) {
                case self::OPTIMIZE_BY_TIME:
                    break;
                case self::OPTIMIZE_BY_DISTANCE:
                    break;
                default:
                    throw new \InvalidArgumentException("Map service configuration optimized by '{$optimizeBy}' is not valid.");
            }
        }

        if ($options) {
            $routeAvoidTypesList = array(
                self::ROUTE_AVOID_LIMITED_ACCESS      => 'LimitedAccess',
                self::ROUTE_AVOID_TOLL_ROAD           => 'Toll',
                self::ROUTE_AVOID_FERRY               => 'Ferry',
                self::ROUTE_AVOID_UNPAVED             => 'Unpaved',
                self::ROUTE_AVOID_SEASONAL_CLOSURE    => 'SeasonalClosure',
                self::ROUTE_AVOID_BORDER_CROSSING     => 'CountryBorder',
                self::ROUTE_AVOID_HIGHWAY             => 'Highway'
            );

            if (! array_intersect($options, $routeAvoidTypesList)) {
                throw new \InvalidArgumentException("Map service configuration avoid options '{$options}' is not valid.");
            }
        }
    }

    /**
     * Get map javascript api url for given country
     *
     * @param string $country country
     * @return string javascript api url
     *
     */
    public function getJavascriptApiUrl($country=null)
    {
        if (! $country) {
            $country = $this->defaultCountry;
        }

        $APIProvider = $this->getJavascriptAPIProvider($country);
        return $APIProvider->getJavascriptApiUrl();
    }

    /**
     * Get map javascript plugin path for given country
     *
     * @param string $country country
     * @return string javascript plugin path
     *
     */
    public function getJavascriptMapPlugin($country=null)
    {
        if (! $country) {
            $country = $this->defaultCountry;
        }

        $APIProvider = $this->getJavascriptAPIProvider($country);
        return $APIProvider->getJavascriptMapPlugin();
    }

    /**
     * Get allowed quality codes for javascript api by given country
     *
     * @param string $country country
     * @return array allowed quality codes
     *
     */
    public function getAllowedQualityCodes($country=null)
    {
        if (! $country) {
            $country = $this->defaultCountry;
        }

        if (! isset($this->geocoders[$country])) {
            throw new \Exception("Can not find geocode provider for country {$country}");
        }

        return $this->geocoders[$country][0]['allowedQualityCodes'];
    }

    /**
     * Get route provider for given country
     *
     * @param string $country country
     * @return array route provider for given country
     *
     */
    protected function getRouteProvider($country)
    {
        if (!isset($this->routers[$country])) {
            throw new \Exception("Can not find route provider for country {$country}");
        }

        $router = $this->routers[$country];
        $routeProvider = $this->providers[$router['providerId']];

        $this->logger->debug("Route provider is {$router['providerId']}.");

        return $routeProvider;
    }

    /**
     * Get time zone provider
     * @param $country
     * @return mixed
     * @throws \Exception
     */
    protected function getTimeZoneProvider($country)
    {
        if (!isset($this->timeZoners[$country])) {
            throw new \Exception("Can not find time zone provider for country {$country}");
        }

        $timeZoner = $this->timeZoners[$country];
        $timeZoneProvider = $this->providers[$timeZoner['providerId']];

        $this->logger->debug("Time zone provider is {$timeZoner['providerId']}.");

        return $timeZoneProvider;
    }

    /**
     * Get javascript api provider for given country
     *
     * @param string $country country
     * @return array route provider for given country
     *
     */
    protected function getJavascriptAPIProvider($country)
    {
        if (! isset($this->apis[$country])) {
            throw new \Exception("Can not find javascript api for country {$country}");
        }

        $javascriptAPI = $this->apis[$country];
        $APIProvider = $this->providers[$javascriptAPI['providerId']];

        $this->logger->debug("Javascript API provider is {$javascriptAPI['providerId']}.");

        return $APIProvider;
    }

    /**
     * Get filtered address by configuration of tokens
     *
     * @param array $address
     * @param array $tokens
     * @return array filtered address by tokens
     *
     */
    protected function filterAddressByTokens($address, $tokens)
    {
        $filteredAddress = array();
        foreach ($tokens as $token) {
            $filteredAddress[$token] = Arr::get($token, $address);
        }

        return $filteredAddress;
    }
}
