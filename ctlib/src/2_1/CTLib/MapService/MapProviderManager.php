<?php
namespace CTLib\MapService;

use Symfony\Component\Yaml\Yaml,
    Symfony\Component\HttpKernel\Config\FileLocator,
    CTLib\Util\Arr;

class MapProviderManager implements MapProviderInterface
{
    const OPTIMIZE_BY_TIME              = 'fastest';
    const OPTIMIZE_BY_DISTANCE          = 'shortest';
    
    const ROUTE_AVOID_LIMITED_ACCESS    = 'LimitedAccess';
    const ROUTE_AVOID_TOLL_ROAD         = 'Toll';
    const ROUTE_AVOID_FERRY             = 'Ferry';
    const ROUTE_AVOID_UNPAVED           = 'Unpaved';
    const ROUTE_AVOID_SEASONAL_CLOSURE  = 'SeasonalClosure';
    const ROUTE_AVOID_BORDER_CROSSING   = 'CountryBorder';


    /**
     * @var string
     */
    protected $defaultCountry;

    /**
     * @var array
     */
    protected $providers;
    
    
    /**
     * @param string $defaultCountry
     * @param Logger $logger
     */
    public function __construct($defaultCountry, $logger)
    {
        $this->defaultCountry   = $defaultCountry;
        $this->logger           = $logger;
        $this->providers        = array();
    }

    /**
     * Register map service provider.
     *
     * @param string $class
     * @param array $countries  Provider will be used for specified countries.
     * @param array $allowedQualityCodes    Acceptable quality codes used when
     *                                      geocoding.
     *
     * @return void
     */
    public function registerProvider($class, $countries, $allowedQualityCodes)
    {
        $provider = new $class($allowedQualityCodes);

        if (! ($provider instanceof MapProviderAbstract)) {
            throw new \Exception("Map provider class '{$class}' has to extend from MapProviderAbstract");
        }

        foreach ($countries as $country) {
            if (isset($this->providers[$country])) {
                throw new \Exception("Cannot assign multiple map providers to country '{$country}'");
            }
            $this->providers[$country] = $provider;
        }
    }

    /**
     * Get map provider for given country
     *
     * @param string $country country
     * @return MapProviderAbstract map provider object that can handle given country
     *
     */
    protected function getMapProviderByCountry($country)
    {
        if ($country === null) {
            $country = $this->defaultCountry;
        }
        
        //find the country key in array of providers, 
        //if not found, find provider by key of *
        //if not found, throw exception
        $mapProvider = Arr::get($country, $this->providers);
        if ($mapProvider) {
            return $mapProvider;
        }

        $mapProvider = Arr::get("*", $this->providers);
        if ($mapProvider) {
            return $mapProvider;
        }

        throw \Exception("Can not find map provider for country {$country}");
    }

    /**
     * {@inheritdoc}
     */    
    public function getJavascriptApiUrl($country=null)
    {
        if ($country === null) {
            $country = $this->defaultCountry;
        }
        return $this
            ->getMapProviderByCountry($country)
            ->getJavascriptApiUrl($country);
    }

    /**
     * {@inheritdoc}
     */    
    public function getJavascriptMapPlugin($country=null)
    {
        if ($country === null) {
            $country = $this->defaultCountry;
        }
        return $this
            ->getMapProviderByCountry($country)
            ->getJavascriptMapPlugin($country);
    }
    
    /**
     * {@inheritdoc}
     */    
    public function geocode($address, $country=null)
    {
        if ($country === null) {
            $country = $this->defaultCountry;
        }

        return $this
                ->getMapProviderByCountry($country)
                ->geocode(
                    array(
                        "street"      => Arr::get("street", $address),
                        "city"        => Arr::get("city", $address),
                        "subdivision" => Arr::get("subdivision", $address),
                        "postalCode"  => Arr::get("postalCode", $address),
                        "country"     => $country
                    ),
                    $country
                );
    }

    /**
     * {@inheritdoc}
     */    
    public function geocodeBatch(array $addresses, $country=null)
    {
        if (!$addresses) { return null; }
        if ($country === null) {
            $country = $this->defaultCountry;
        }

        //clean up the addresses
        $addresses = array_map(
            function($address) use ($country) {
                return array(
                    "street"      => $address["street"],
                    "city"        => $address["city"],
                    "subdivision" => $address["subdivision"],
                    "postalCode"  => $address["postalCode"],
                    "country"     => $country
                );
            },
            $addresses
        );

        return $this
                ->getMapProviderByCountry($country)
                ->geocodeBatch($addresses, $country);
    }

    /**
     * {@inheritdoc}
     */    
    public function reverseGeocode($latitude, $longitude, $country=null)
    {
        if ($country === null) {
            $country = $this->defaultCountry;
        }

        return $this
                ->getMapProviderByCountry($country)
                ->reverseGeocode($latitude, $longitude, $country);
    }

    /**
     * {@inheritdoc}
     */    
    public function reverseGeocodeBatch(array $latLngs, $country=null)
    {
        if ($country === null) {
            $country = $this->defaultCountry;
        }

        return $this
                ->getMapProviderByCountry($country)
                ->reverseGeocodeBatch($latLngs, $country);
    }

    /**
     * {@inheritdoc}
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
        if ($country === null) {
            $country = $this->defaultCountry;
        }

        return $this
                ->getMapProviderByCountry($country)
                ->route(
                    $fromLatitude,
                    $fromLongitude,
                    $toLatitude,
                    $toLongitude,
                    $optimizeBy,
                    $options,
                    $country);
    }

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
    public function routeTimeAndDistance(
                        $fromLatitude,
                        $fromLongitude,
                        $toLatitude,
                        $toLongitude,
                        $optimizeBy,
                        array $options=array(),
                        $country=null)
    {
        return $this
                ->getMapProviderByCountry($country)
                ->routeTimeAndDistance(
                    $fromLatitude,
                    $fromLongitude,
                    $toLatitude,
                    $toLongitude,
                    $optimizeBy,
                    $options,
                    $country);
    }

    public function getAllowedQualityCodes($country=null)
    {
        return $this
                ->getMapProviderByCountry($country)
                ->getAllowedQualityCodes();
    }
}
