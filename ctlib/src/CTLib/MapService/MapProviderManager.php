<?php

namespace CTLib\MapService;

use Symfony\Component\Yaml\Yaml,
    Symfony\Component\HttpKernel\Config\FileLocator,
    CTLib\Util\Arr;

class MapProviderManager implements MapProviderInterface
{
    protected $container;
    protected $siteConfig;
    protected $countryInSiteConfig;
    protected $providersByCountry = array();
    protected $providers = array();

    public function __construct($container, $siteConfig)
    {
        $this->container = $container;
        $this->siteConfig = $siteConfig;
        $this->countryInSiteConfig = "US";//Fixme: $siteConfig->get("geo.country_code");

        $mapProviderConfig = $container->getParameter('ctlib.map_service.providers');
        if (!$mapProviderConfig) {
            throw new \Exception("Providers have to be configured for map service");
        }
        $this->registerMapProviders($mapProviderConfig);
    }

    /**
     * Register map providers to countries based on what is configured in yml
     *
     * @param array $mapProviderConfigs config returned by loadConfig
     * @return void
     *
     */
    protected function registerMapProviders($mapProviderConfigs)
    {
        foreach ($mapProviderConfigs as $config)
        {
            $mapProviderClass = "\\" . trim($config["class"], "\\");
            if (!class_exists($mapProviderClass)) {
                throw new \Exception("Class {$config["class"]} does not exist!");
            }

            if (!isset($config["country"]) || !is_array($config["country"])) {
                throw new \Exception("Config Country has to be an array");
            }

            if (!isset($config["allowedAddressQualityCodes"]) || !is_array($config["allowedAddressQualityCodes"])) {
                throw new \Exception("Allowed Qualitied codes are invalid");
            }

            $mapProvider = new $config["class"]($config["allowedAddressQualityCodes"]);

            if (!$mapProvider instanceof MapProviderAbstract) {
                throw new \Exception("{$config["class"]} has to be extended from MapProviderAbstract");
            }

            foreach ($config["country"] as $country) {
                $country = trim($country);
                if (array_key_exists($country, $this->providersByCountry)) {
                    throw new \Exception("Could not have more than one * in country");
                }
                $this->providersByCountry[$country] = $mapProvider;
            }
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
        //find the country key in array of providersByCountry, 
        //if not found, find provider by key of *
        //if not found, throw exception
        $mapProvider = Arr::get($country, $this->providersByCountry);
        if ($mapProvider) {
            return $mapProvider;
        }

        $mapProvider = Arr::get("*", $this->providersByCountry);
        if ($mapProvider) {
            return $mapProvider;
        }

        throw \Exception("Can not find map provider for country {$country}");
    }

    public static function getJavascriptApiUrl() {}

    public function geocode($address, $country = null)
    {
        if ($country === null) {
            $country = $this->countryInSiteConfig;
        }

        return $this
            ->getMapProviderByCountry($country)
            ->geocode(
                array(
                    "street"      => $address["street"],
                    "city"        => $address["city"],
                    "subdivision" => $address["subdivision"],
                    "postalCode"  => $address["postalCode"],
                    "country"     => $country
                ),
                $country
            );
    }

    public function geocodeBatch(array $addresses, $country = null)
    {
        //if country is not given in the parameter, use country code 
        //configed in the site config
        if ($country === null) {
            $country = $this->countryInSiteConfig;
        }

        if (!$addresses) { return null; }

        //clean up the 
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

    public function reverseGeocode($latitude, $longitude, $country = null)
    {
        //if country is not given in the parameter, use country code 
        //configed in the site config
        if ($country === null) {
            $country = $this->countryInSiteConfig;
        }

        return $this
            ->getMapProviderByCountry($country)
            ->reverseGeocode($latitude, $longitude, $country);
    }

    public function reverseGeocodeBatch(array $latLngs, $country = null)
    {
        //if country is not given in the parameter, use country code 
        //configed in the site config
        if ($country === null) {
            $country = $this->countryInSiteConfig;
        }

        return $this
            ->getMapProviderByCountry($country)
            ->reverseGeocodeBatch($latLngs, $country);
    }

    public function route($fromLatitude, $fromLongitude, $toLatitude, $toLongitude, array $options, $country = null)
    {
        //if country is not given in the parameter, use country code 
        //configed in the site config
        if ($country === null) {
            $country = $this->countryInSiteConfig;
        }

        return $this
            ->getMapProviderByCountry($country)
            ->route($fromLatitude, $fromLongitude, $toLatitude, $toLongitude, $options, $country);
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
    public function routeTimeAndDistance($fromLatitude, $fromLongitude,
        $toLatitude, $toLongitude, array $options=array(), $country=null)
    {
        $country = $country ?: $this->countryInSiteConfig;

        return $this
                ->getMapProviderByCountry($country)
                ->routeTimeAndDistance(
                    $fromLatitude,
                    $fromLongitude,
                    $toLatitude,
                    $toLongitude,
                    $options,
                    $country);
    }

    public function getAllowedAddressQualityCodes($country = null)
    {
        //if country is not given in the parameter, use country code 
        //configed in the site config
        if ($country === null) {
            $country = $this->countryInSiteConfig;
        }

        return $this
            ->getMapProviderByCountry($country)
            ->getAllowedAddressQualityCodes();
    }
}