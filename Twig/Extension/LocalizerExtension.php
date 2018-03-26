<?php
namespace CTLib\Twig\Extension;


class LocalizerExtension extends \Twig_Extension
{
    
    /**
     * @var Localizer
     */
    protected $localizer;

    
    /**
     * @param Localizer $localizer
     */
    public function __construct($localizer)
    {
        $this->localizer = $localizer;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'localizer';
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            'distanceUnit'  => new \Twig_Function_Method($this, 'getCountryDistanceUnit'),
            'subdivisions'  => new \Twig_Function_Method($this, 'getSubdivisionsFromLocale'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            'formatDatetime'    => new \Twig_Filter_Method($this, 'formatDatetime'),
            'longDate'          => new \Twig_Filter_Method($this, 'longDate'),
            'shortDate'         => new \Twig_Filter_Method($this, 'shortDate'),
            'casualDate'        => new \Twig_Filter_Method($this, 'casualDate'),
            'longTime'          => new \Twig_Filter_Method($this, 'longTime'),
            'shortTime'         => new \Twig_Filter_Method($this, 'shortTime'),
            'casualTime'        => new \Twig_Filter_Method($this, 'casualTime'),
            'shortDateTime'     => new \Twig_Filter_Method($this, 'shortDateTime'),
            'longDateTime'      => new \Twig_Filter_Method($this, 'longDateTime'),
            'casualDateTime'    => new \Twig_Filter_Method($this, 'casualDateTime'),
            'currency'          => new \Twig_Filter_Method($this, 'currency'),
            'shortDistance'     => new \Twig_Filter_Method($this, 'shortDistance'),
            'fullDistance'      => new \Twig_Filter_Method($this, 'fullDistance'),
        );
    }

    /**
     * get Country Distance Unit
     *
     * @return string distance unit
     *
     */
    public function getCountryDistanceUnit()
    {
        return $this->localizer->getCountryDistanceUnit();
    }

    /**
     * get Subdivisions From Locale
     *
     * @param string $locale locale
     * @return array subdivisions (states or provinces)
     *
     */
    public function getSubdivisionsFromLocale($locale=null)
    {
        return $this->localizer->getSubdivisionsFromLocale($locale);
    }

    /**
     * Proxy for LocalizationHelper::formatDatetime.
     *
     * @param int $timestamp
     * @param string $format
     * @param string $locale
     * @param string $timezone
     *
     * @return string String representing the formatted date value
     */
    public function formatDatetime($timestamp, $format, $locale=null, $timezone=null)
    {
        return $this
                ->localizer
                ->formatDatetime($timestamp, $format, $locale, $timezone);
    }

    /**
     * Proxy for LocalizationHelper::longDate.
     *
     * @param int $timestamp
     * @param string $locale
     * @param string $timezone
     *
     * @return string String representing the formatted date value
     */
    public function longDate($timestamp, $locale=null, $timezone=null)
    {
        return $this->localizer->longDate($timestamp, $locale, $timezone);
    }

    /**
     * Proxy for LocalizationHelper::shortDate.
     *
     * @param int $timestamp
     * @param string $locale
     * @param string $timezone
     *
     * @return string String representing the formatted date value
     */
    public function shortDate($timestamp, $locale=null, $timezone=null)
    {
        return $this->localizer->shortDate($timestamp, $locale, $timezone);
    }

    /**
     * Proxy for LocalizationHelper::casualDate.
     *
     * @param int $timestamp
     * @param string $locale
     * @param string $timezone
     *
     * @return string String representing the formatted date value
     */
    public function casualDate($timestamp, $locale=null, $timezone=null)
    {
        return $this->localizer->casualDate($timestamp, $locale, $timezone);
    }

    /**
     * Proxy for LocalizationHelper::longTime.
     *
     * @param int $timestamp
     * @param string $locale
     * @param string $timezone
     *
     * @return string String representing the formatted date value
     */
    public function longTime($timestamp, $locale=null, $timezone=null)
    {
        return $this->localizer->longTime($timestamp, $locale, $timezone);
    }

    /**
     * Proxy for LocalizationHelper::shortTime.
     *
     * @param int $timestamp
     * @param string $locale
     * @param string $timezone
     *
     * @return string String representing the formatted date value
     */
    public function shortTime($timestamp, $locale=null, $timezone=null)
    {
        return $this->localizer->shortTime($timestamp, $locale, $timezone);
    }

    /**
     * Proxy for LocalizationHelper::casualTime.
     *
     * @param int $timestamp
     * @param string $locale
     * @param string $timezone
     *
     * @return string String representing the formatted date value
     */
    public function casualTime($timestamp, $locale=null, $timezone=null)
    {
        return $this->localizer->casualTime($timestamp, $locale, $timezone);
    }

    /**
     * Proxy for LocalizationHelper::shortDateTime.
     *
     * @param int $timestamp
     * @param string $locale
     * @param string $timezone
     *
     * @return string String representing the formatted date value
     */
    public function shortDateTime($timestamp, $locale=null, $timezone=null)
    {
        return $this->localizer->shortDateTime($timestamp, $locale, $timezone);
    }

    /**
     * Proxy for LocalizationHelper::longDateTime.
     *
     * @param int $timestamp
     * @param string $locale
     * @param string $timezone
     *
     * @return string String representing the formatted date value
     */
    public function longDateTime($timestamp, $locale=null, $timezone=null)
    {
        return $this->localizer->longDateTime($timestamp, $locale, $timezone);
    }

    /**
     * Proxy for LocalizationHelper::casualDateTime.
     *
     * @param int $timestamp
     * @param string $locale
     * @param string $timezone
     *
     * @return string String representing the formatted date value
     */
    public function casualDateTime($timestamp, $locale=null, $timezone=null)
    {
        return $this->localizer->casualDateTime($timestamp, $locale, $timezone);
    }

    /**
    * Proxy for LocalizationHelper::currency.
    *
    * @param float $value
    * @param string $locale
    * @param string $currency
    *
    * @return string String representing the formatted currency value
    */
    public function currency($value, $locale=null, $currency=null)
    {
        return $this->localizer->currency($value, $locale, $currency);
    }

    /**
     * Proxy for LocalizationHelper::shortDistance
     *
     * @param float $distance distance value
     * @param locale $locale locale
     * @return mixed This is the return value description
     *
     */
    public function shortDistance($distance, $locale=null)
    {
        return $this->localizer->shortDistance($distance, $locale);
    }

    /**
     * Proxy for LocalizationHelper::fullDistance
     *
     * @param float $distance distance value
     * @param locale $locale locale
     * @return string localized distance in full format
     *
     */    
    public function fullDistance($distance, $locale=null)
    {
        return $this->localizer->fullDistance($distance, $locale);
    }
    
}