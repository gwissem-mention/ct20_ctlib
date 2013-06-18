<?php

namespace CTLib\Helper;

use Symfony\Component\HttpFoundation\Session,
    Symfony\Component\Yaml\Yaml,
    CTLib\Util\Util,
    CTLib\Util\Arr;

/**
 * Helps localizing date, time and currency
 */
class LocalizationHelper
{
    const CONFIG_TYPE_LOCALE        = "locale";
    const CONFIG_TYPE_COUNTRY       = "country";

    const DISTANCE_UNIT_KILOMETER   = "kilometer";
    const DISTANCE_UNIT_MILE        = "mile";

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * Stores session object
     *
     * @var \Symfony\Component\HttpFoundation\Session
     */
    protected $session;

    /**
     * Stores loaded localization array
     *
     * @var array
     */
    protected $configs;

    /**
     * Stores translator
     *
     * @var Translator
     */
    protected $translator;

    /**
     * Constructor
     *
     * @param Cache      $cache
     * @param Translator $translator
     * @param Session    $session
     */
    public function __construct($cache, $translator, $session=null)
    {
        $this->cache    = $cache;
        $this->session  = $session;
        $this->configs  = array();
        $this->translator   = $translator;
    }

    /**
     * Returns config value for given config name.
     *
     * @param string $configType Config type you want to work with.
     * @param string $configCode Item for each config name, ex. en_US, US
     * @param string $configKey  Key configured in the yaml file
     *
     * @return mixed
     * @throws Exception    If $configKey not found in given config name.
     */
    protected function getConfigValue($configType, $configCode, $configKey)
    {
        $configs = $this->loadConfig($configType, $configCode);
        
        if (! Arr::existByKeyChain($configs, $configKey)) {
            throw new \Exception("Invalid config key: $configKey");
        }

        $configValue = Arr::findByKeyChain(
            $configs,
            $configKey
        );

        return $configValue;
    }

    /**
     * Loads configuration.
     *
     * @param string $configType Config type you want to work with.
     * @param string $configCode Item for each config name, ex. en_US, US
     *
     * @return array    Returns configuration array.
     */
    protected function loadConfig($configType, $configCode)
    {
        if (! isset($this->configs[$configType][$configCode])) {
            $cacheKey = $configType . "Config." . $configCode;
            $config = $this->cache->get($cacheKey);

            if (! $config) {
                $config = $this->fetchConfig($configType, $configCode);
                $this->cache->set($cacheKey, $config);
            }

            $this->configs[$configType] = array($configCode => $config);
        }
        return $this->configs[$configType][$configCode];
    }

    /**
     * Fetches fresh configuration from source.
     *
     * @param string $configType Config type you want to work with.
     * @param string $configCode Item for each config name, ex. en_US, US
     *
     * @return array
     * @throws \Exception   If config file cannot be parsed.
     */
    protected function fetchConfig($configType, $configCode)
    {
        if ($configType != self::CONFIG_TYPE_LOCALE
            && $configType != self::CONFIG_TYPE_COUNTRY) {
            throw new \Exception("Invalid config: {$configType}.{$configCode}");
        }

        $configPath = __DIR__ . "/../Resources/localization/"
                    . "{$configType}/{$configCode}.yml";
        $configYaml = file_get_contents($configPath);

        if (! $configYaml) {
            throw new \Exception("Missing or empty config file: $configPath");
        }
        return Yaml::parse($configYaml);
    }

    /**
     * Retrieves locale property from Session.
     *
     * @return string
     * @throws \Exception   If Session not set or doesn't have locale set.
     */
    protected function getSessionLocale()
    {
        if (! $this->session) {
            throw new \Exception('Session not set.');
        }
        if (! $this->session->getLocale()) {
            throw new \Exception('locale not set in Session.');
        }
        return $this->session->getLocale();
    }

    /**
     * Get Country code out of locale
     *
     * @param string $locale Locale
     *
     * @return string Country Code
     */
    public function getCountryCodeFromLocale($locale)
    {
        $localeTokens = explode("_", $locale);
        if (count($localeTokens) != 2 || empty($localeTokens[1])) {
            throw new \Exception("Invalid locale: $locale");
        }
        return $localeTokens[1];
    }

    /**
     * Retrieves country property from locale in session.
     *
     * @return string
     * @throws \Exception   If Session not set or doesn't have locale set.
     */
    public function getSessionCountryCode()
    {
        return $this->getCountryCodeFromLocale($this->getSessionLocale());
    }

    /**
     * Returns config value for locale.
     *
     * @param string $configKey     Will tokenize on '.' for nested configs.
     * @param string $locale
     *
     * @return mixed
     * @throws Exception    If $configKey not found in locale's config.
     */
    public function getLocaleConfigValue($configKey, $locale = null)
    {
        $locale = $locale ?: $this->getSessionLocale();
        return $this->getConfigValue(
            self::CONFIG_TYPE_LOCALE,
            $locale,
            $configKey
        );
    }

    /**
     * Returns config value for country
     *
     * @param string $configKey Will tokenize on '.' for nested configs
     * @param string $countryCode Country code
     *
     * @return mixed
     */
    public function getCountryConfigValue($configKey, $countryCode = null)
    {
        $countryCode = $countryCode ?: $this->getSessionCountryCode();
        return $this->getConfigValue(
            self::CONFIG_TYPE_COUNTRY,
            $countryCode,
            $configKey
        );
    }

    /**
     * Formats timestamp or DateTime into passed format based on locale and
     * timezone.
     *
     * @param mixed   $value
     * @param string  $format    See http://goo.gl/zeaE8.
     * @param string  $locale    If null, will use Session's locale.
     * @param mixed   $timezone  See http://php.net/manual/en/timezones.php.
     *                           If null, will use Session's timezone.
     *
     * @return string
     * @throws \Exception       If formatting process fails.
     */
    public function formatDatetime($value, $format, $locale=null, $timezone=null)
    {
        $locale = $locale ?: $this->getSessionLocale();
        
        if (! $timezone instanceof \DateTimeZone) {
            $timezone = new \DateTimeZone($timezone ?: $this->getSessionTimezone());
        }

        // Only needed for PHP <5.3.4
        if ($value instanceof \DateTime) {
            $value = $value->getTimestamp();
        }

        $formatter = new \IntlDateFormatter(
            $locale,
            null,
            null,
            $timezone->getName(),
            null,
            $format
        );

        if ($formatter === false) {
            throw new \Exception("Could not compile format: $format. \nError: " . $formatter->getErrorMessage());
        }

        if (is_string($value)) {
            $value = (int) $value;
        }

        $formattedDatetime = $formatter->format($value);

        if ($formattedDatetime === false) {
            if ($value instanceof \DateTime) {
                $value = "{DateTime: {$value->format('c')}}";
            }
            throw new \Exception("Could not format: $value. \nError: " . $formatter->getErrorMessage());
        }
        return $formattedDatetime;
    }

    /**
     * Formats timestamp into stored format based on locale and timezone.
     *
     * @param mixed   $value
     * @param string  $formatKey Must match datetime.{$formatKey} in config.
     * @param string  $locale    If null, will use Session's locale.
     * @param string  $timezone  See http://php.net/manual/en/timezones.php.
     *                           If null, will use Session's timezone.
     *
     * @return string
     * @throws \Exception       If formatting process fails.
     */
    protected function formatDatetimeByKey($value, $formatKey, $locale=null,
        $timezone=null)
    {
        $format = $this->getLocaleConfigValue(
            "datetime.formats.{$formatKey}",
            $locale
        );
        return $this->formatDatetime($value, $format, $locale, $timezone);
    }

    /**
     * Handles formatting of timestamp into format specified in method name.
     *
     * For example, LocalizationHelper::longDate will format timestamp using
     * 'longDate' format.
     *
     * Signature: {format}($timestamp, $locale=null, $timezone=null)
     */
    public function __call($methodName, $args)
    {
        if (Util::endsWith($methodName, 'DateTime')) {
            $format = substr($methodName, 0, -8);
            $value  = Arr::mustGet(0, $args);
            $locale = Arr::get(1, $args);
            $tz     = Arr::get(2, $args);
            $showTz = Arr::get(3, $args);

            $fmtDate = $this->formatDatetimeByKey(
                            $value,
                            "{$format}Date",
                            $locale,
                            $tz
                        );
            $fmtTime = $this->formatDatetimeByKey(
                            $value,
                            "{$format}Time",
                            $locale,
                            $tz
                        );

            if (! $showTz) {
                return sprintf('%s %s', $fmtDate, $fmtTime);
            } else {
                $fmtTz = $this->formatDatetimeByKey(
                                $value,
                                "{$format}Timezone",
                                $locale,
                                $tz
                            );
                return sprintf('%s %s (%s)', $fmtDate, $fmtTime, $fmtTz);
            }
        }

        if (Util::endsWith($methodName, 'Date')
            || Util::endsWith($methodName, 'Time')
            || Util::endsWith($methodName, 'Timezone')
        ) {
            // Return formatted timestamp.
            $format = $methodName;
            $value  = Arr::mustGet(0, $args);
            $locale = Arr::get(1, $args);
            $tz     = Arr::get(2, $args);
            $showTz = Arr::get(3, $args);

            $fmt = $this->formatDatetimeByKey($value, $format, $locale, $tz);

            if (! $showTz) {
                return $fmt;
            } else {
                $format = substr($format, 0, -4) . "Timezone";
                $fmtTz = $this->formatDatetimeByKey(
                                $value,
                                $format,
                                $locale,
                                $tz
                            );
                return sprintf('%s (%s)', $fmt, $fmtTz);
            }
        }

        throw new \Exception("Invalid method: {$methodName}");
    }

    /**
     * Format float number into currency
     *
     * @param float   $value Value
     * @param string  $locale Locale
     * @param string  $currency The 3-letter ISO 4217 currency code see http://www.xe.com/iso4217.php#U
     *
     * @return string String representing the formatted currency value
     */
    public function currency($value, $locale=null, $currency=null)
    {
        $locale     = $locale ?: $this->getSessionLocale();
        $currency   = $currency ?: $this->getLocaleConfigValue('number.currency', $locale);

        return numfmt_format_currency(
            numfmt_create($locale, \NumberFormatter::CURRENCY),
            $value,
            $currency
        );
    }

    /**
     * Format the number into given format
     *
     * @param float   $number  Number value
     * @param string  $locale  Locale
     *
     * @return string Localized number in the format given by parameter.
     */
    public function formatNumber($number, $locale = null)
    {
        $fmt = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $fmt->setPattern(
            $this->getLocaleConfigValue("number.format", $locale)
        );

        $result = $fmt->format((float) $number);
        if ($result === false) {
            throw new \Exception("Can not format {$number} into the format of {$format}.");
        }
        return $result;
    }

    /**
     * Format the duration in second into localized minutes
     *
     * @param integer $second  Time in seconds.
     * @param string  $locale  Locale.
     *
     * @return string Localized duration in minutes.
     */
    public function durationShortMinute($second, $locale=null)
    {
        $minutes = (int) ($second/60);
        return $this->translator->trans(
            "time.minute.short",
            array(
                "%minute%" => $this->formatNumber($minutes)
            )
        );
    }

    /**
     * Format the duration in second into localized full minutes
     *
     * @param integer $second  Time in seconds.
     * @param string  $locale  Locale.
     *
     * @return string Localized duration in full minutes.
     */
    public function durationFullMinute($second, $locale=null)
    {
        $minutes = (int) ($second/60);
        return $this->translator->transChoice(
            "time.minute.full",
            $minutes,
            array(
                "%minute%" => $this->formatNumber($minutes)
            )
        );
    }

    /**
     * Format the duration in second into localized hours
     *
     * @param integer $second  Duration in seconds.
     * @param string  $locale  Locale
     *
     * @return string localized duration in hours
     */
    public function durationShortHour($second, $locale=null)
    {
        $hours = (int) ($second/3600);
        return $this->translator->trans(
            "time.minute.short",
            array(
                "%hour%" => $this->formatNumber($hours)
            )
        );
    }

    /**
     * Format the duration in second into localized seconds
     *
     * @param integer $second Duration in seconds.
     * @param string  $locale Locale
     *
     * @return string Localized duration in seconds.
     */
    public function durationShortSecond($second, $locale=null)
    {
        return $this->translator->trans(
            "time.second.short",
            array(
                "%second%" => $this->formatNumber((int) $second)
            )
        );
    }

    /**
     * Format the distance into short format
     *
     * @param float $distance Distance value.
     * @param locale $locale  Locale
     *
     * @return string Localized distance in short format.
     */
    public function shortDistance($distance, $locale=null)
    {
        $countryDistanceUnit = $this->getCountryDistanceUnitFromLocale($locale);
        return $this->translator->trans(
            "distance.{$countryDistanceUnit}.short",
            array(
                "%distance%" => $this->formatNumber($distance)
            )
        );
    }

    /**
     * Format the distance into full format
     *
     * @param float $distance Distance value.
     * @param locale $locale  Locale
     *
     * @return string Localized distance in full format.
     */
    public function fullDistance($distance, $locale=null)
    {
        $countryDistanceUnit = $this->getCountryDistanceUnitFromLocale($locale);
        return $this->translator->transChoice(
            "distance.{$countryDistanceUnit}.full",
            (float) $distance,
            array(
                "%distance%" => $this->formatNumber($distance)
            )
        );
    }

    /**
     * Get short distance unit
     *
     * @param string $locale Locale
     *
     * @return string distance unit
     */
    public function getDistanceShortUnit($locale=null)
    {
        $countryDistanceUnit = $this->getCountryDistanceUnitFromLocale($locale);
        return $this->translator->trans("distance.{$countryDistanceUnit}.short_unit");
    }

    /**
     * Get full distance unit
     *
     * @param integer $distance Distance value.
     * @param string  $locale   Locale
     *
     * @return string Pluralized distance unit (default is singlton).
     */
    public function getDistanceFullUnit($distance = 1, $locale = null)
    {
        $countryDistanceUnit = $this->getCountryDistanceUnitFromLocale($locale);
        return $this->translator->transChoice(
            "distance.{$countryDistanceUnit}.full_unit",
            $distance
        );
    }

    /**
     * Retrieves timezone property from Session.
     *
     * @return string
     * @throws \Exception   If Session not set or doesn't have timezone set.
     */
    protected function getSessionTimezone()
    {
        if (! $this->session) {
            throw new \Exception('Session not set.');
        }
        if (! $this->session->has('timezone')) {
            throw new \Exception("'timezone' not set in Session.");
        }
        return $this->session->get('timezone');
    }

    /**
     * Get Translator
     *
     * @return Translator
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * Get Distance Unit configured in country yml file
     *
     * @param string $countryCode Country code, if it is missing, get it from locale.
     *
     * @return string distance unit (km or mi)
     */
    public function getCountryDistanceUnit($countryCode = null)
    {
        $distanceUnit = $this->getCountryConfigValue("distance.unit", $countryCode);
        if ($distanceUnit != self::DISTANCE_UNIT_KILOMETER
            && $distanceUnit != self::DISTANCE_UNIT_MILE)
        {
            throw new \Exception("distance unit configured in yaml is invalid");
        }
        return $distanceUnit;
    }

    /**
     * Get Distance Unit configured in country yml file from locale
     *
     * @param string $locale Locale, if it is missing, get it from session.
     *
     * @return string Distance unit (km or mi)
     */
    public function getCountryDistanceUnitFromLocale($locale = null)
    {
        $countryCode = null;
        if (!empty($locale)) {
            $countryCode = $this->getCountryCodeFromLocale($locale);
        }

        return $this->getCountryDistanceUnit($countryCode);
    }

    /**
     * Get Subdivisions (states or provinces
     *
     * @param string $locale Locale
     *
     * @return array array of subdivisions (code, fullname)
     */
    public function getSubdivisionsFromLocale($locale = null)
    {
        return $this->getLocaleConfigValue("subdivisions", $locale);
    }

    /**
     * Formatted phone number
     *
     * @param mixed $phone
     *
     * @todo function to format phone number
     * @return void
     */
    public function formattedPhone($phone)
    {

    }

    /**
     * format the raw address array into abbreviated single line address
     * address format is configured in country localization file like US.yml
     *
     * @param array $rawAddress array of address return by getRawAddress
     * @param string $countryCode country code
     * @return string single line address
     *
     */
    public function abbreviatedAddress($rawAddress, $countryCode = null)
    {
        return $this->formatSingleLineAddress(
            $this->getCountryConfigValue("address.abbreviated", $countryCode),
            $rawAddress,
            $countryCode
        );
    }

    /**
     * format the raw address array into short single line address
     * address format is configured in country localization file like US.yml
     *
     * @param array $rawAddress array of address return by getRawAddress
     * @param string $countryCode country code
     * @return string single line address
     *
     */
    public function shortSingleLineAddress($rawAddress, $countryCode = null)
    {
        return $this->formatSingleLineAddress(
            $this->getCountryConfigValue("address.shortSingleLine", $countryCode),
            $rawAddress,
            $countryCode
        );
    }

    /**
     * format the raw address array into long single line address
     * address format is configured in country localization file like US.yml
     *
     * @param array $rawAddress array of address return by getRawAddress
     * @param string $countryCode country code
     * @return string single line address
     *
     */
    public function longSingleLineAddress($rawAddress, $countryCode = null)
    {
        return $this->formatSingleLineAddress(
            $this->getCountryConfigValue("address.longSingleLine", $countryCode),
            $rawAddress,
            $countryCode
        );
    }


    /**
     * format raw address into short multiple line address,
     * format is specified in country config yaml like US.yml
     *
     * @param array $rawAddress array of raw address
     * @param string $countryCode country code
     * @return array array of formatted address
     *
     */
    public function shortMultipleLineAddress($rawAddress, $countryCode = null)
    {
        return $this->formatMultipleLineAddress(
            $this->getCountryConfigValue("address.shortMultipleLine", $countryCode),
            $rawAddress,
            $countryCode
        );
    }

    /**
     * format raw address into long multiple line address
     * format is specified in country config yaml like US.yml
     *
     * @param array $rawAddress array of raw address
     * @param string $countryCode country code
     * @return array array of formatted address
     *
     */
    public function longMultipleLineAddress($rawAddress, $countryCode = null)
    {
        return $this->formatMultipleLineAddress(
            $this->getCountryConfigValue("address.longMultipleLine", $countryCode),
            $rawAddress,
            $countryCode
        );
    }

    /**
     * format raw address into short multiple line address,
     * format is specified in country config yaml like US.yml
     *
     * @param array $rawAddress array of raw address
     * @param string $countryCode country code
     * @return array array of formatted address
     *
     */
    public function shortMultipleWithName($rawAddress, $countryCode = null)
    {
        return $this->formatMultipleLineAddress(
            $this->getCountryConfigValue('address.shortMultipleWithName', $countryCode),
            $rawAddress,
            $countryCode
        );
    }

    /**
     * Returns locales defined for country.
     *
     * @param string $countryCode       If null, will use session's country.
     * @return array
     */
    public function getCountryLocales($countryCode=null)
    {
        return $this->getCountryConfigValue('locales', $countryCode);
    }

    /**
     * Returns timezones defined for locale.
     *
     * @param string $locale       If null, will use session's locale.
     * @return array
     */
    public function getTimezonesFromLocale($locale=null)
    {
        return $this->getLocaleConfigValue('timezones', $locale);
    }

    /**
     * Get lat and lng of country's center
     *
     * @param string $countryCode If null, will use session's country.
     * @return array array of (lat, lng)
     *
     */
    public function getMapCenter($countryCode = null)
    {
        $center = $this->getCountryConfigValue("map.center", $countryCode);
        if (empty($center)) {
            throw new \Exception("map center is not configured in yaml correctly");
        }
        return $center;
    }

    /**
     * Get zoom level for country's center
     *
     * @param string $countryCode If null, will use session's country.
     * @return float
     *
     */
    public function getMapZoom($countryCode = null)
    {
        $zoom = $this->getCountryConfigValue("map.zoom", $countryCode);
        if (!isset($zoom)) {
            throw new \Exception("map zoom is not configured in yaml correctly");
        }
        return floatval($zoom);
    }

    /**
     * format raw address array into single line address based on given format
     *
     * @param string $singleLineAddressFormat format string for single line address
     * @param array $rawAddress array of raw address
     * @return string single line address
     *
     */
    private function formatSingleLineAddress($singleLineAddressFormat, $rawAddress, $countryCode = null)
    {
        $separator = $this->getCountryConfigValue("address.separator", $countryCode);

        foreach ($rawAddress as $part => $value) {
            $singleLineAddressFormat = str_replace(
                "%{$part}%",
                $value,
                $singleLineAddressFormat
            );
        }

        $pattern = "/{$separator}\s*{$separator}\s*/";
        while (preg_match($pattern, $singleLineAddressFormat)) {
            $singleLineAddressFormat = preg_replace($pattern, "{$separator} ", $singleLineAddressFormat);
        }

        return trim($singleLineAddressFormat, "{$separator} ");
    }


    /**
     * format array of raw adddress into multiple line address,
     * each line will be item in a result array
     *
     * @param array $multipleLineAddressFormat format for each line
     * @param array $rawAddress array of address
     * @param string $countryCode country code
     * @return array array of addresss, each item will be a line for address
     *
     */
    private function formatMultipleLineAddress($multipleLineAddressFormat, $rawAddress, $countryCode = null)
    {
        $separator = $this->getCountryConfigValue("address.separator", $countryCode);

        $formattedMultipleLineAddress = array();
        foreach ($multipleLineAddressFormat as $line) {
            $line = $this->formatSingleLineAddress($line, $rawAddress);
            if (!empty($line)) {
                $formattedMultipleLineAddress[] = $line;
            }
        }

        return $formattedMultipleLineAddress;
    }

}
