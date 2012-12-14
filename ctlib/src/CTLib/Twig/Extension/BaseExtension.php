<?php

namespace CTLib\Twig\Extension;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Bundle\TwigBundle\Loader\FilesystemLoader;
use CTLib\Util\Arr;

class BaseExtension extends \Twig_Extension
{
    protected $assetHelper;
    protected $localizer;
    protected $jsHelper;
    protected $brandName;
    protected $controller;

    public function __construct($assetHelper, $localizer, $jsHelper,
        $brandName=null)
    {
        $this->assetHelper  = $assetHelper;
        $this->localizer    = $localizer;
        $this->jsHelper     = $jsHelper;
        $this->brandName    = $brandName;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            'globalCss'      => new \Twig_Function_Method($this, 'globalCss'),
            'brandCss'       => new \Twig_Function_Method($this, 'brandCss'),
            'gatewayCss'     => new \Twig_Function_Method($this, 'brandCss'),
            'appCss'         => new \Twig_Function_Method($this, 'appCss'),
            'globalJs'       => new \Twig_Function_Method($this, 'globalJs'),
            'appJs'          => new \Twig_Function_Method($this, 'appJs'),
            'brandJs'        => new \Twig_Function_Method($this, 'brandJs'),
            'gatewayJs'      => new \Twig_Function_Method($this, 'brandJs'),
            'appAsset'       => new \Twig_Function_Method($this, 'appAsset'),
            'pageDOMId'      => new \Twig_Function_Method($this, 'pageDOMId'),
            'jsTranslations' => new \Twig_Function_Method($this, 'jsTranslations'),
            'jsValues'       => new \Twig_Function_Method($this, 'jsValues'),
            'jsRoutes'       => new \Twig_Function_Method($this, 'jsRoutes'),
            'jsPermissions'  => new \Twig_Function_Method($this, 'jsPermissions'),
            'brandName'      => new \Twig_Function_Method($this, 'brandName'),
            'routeUrl'       => new \Twig_Function_Method($this, 'routeUrl')
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
            'bool'              => new \Twig_Filter_Method($this, 'bool'),
        );
    }

    

    public function setController($controller)
    {
        $this->controller = $controller;
    }

    /**
     * Creates HTML for linking to a global CSS file.
     *
     * @param string $filename,...
     * @return string
     */
    public function globalCss($filename)
    {
        if (func_num_args() > 1) {
            $links = array_map(array($this, 'globalCss'), func_get_args());
            return join("\n", $links);
        }
        return $this->assetHelper->buildGlobalCssLink($filename);
    }

    /**
     * Creates HTML for linking to an AppBundle CSS file.
     *
     * @param string $filename,...
     * @return string
     */
    public function appCss($filename)
    {
        if (func_num_args() > 1) {
            $links = array_map(array($this, 'appCss'), func_get_args());
            return join("\n", $links);
        }
        return $this->assetHelper->buildAppCssLink($filename);
    }

    /**
     * Creates HTML for linking to a GatewayBundle CSS file.
     *
     * @param string $filename,...
     * @return string
     */
    public function brandCss($filename)
    {
        if (func_num_args() > 1) {
            $links = array_map(array($this, 'brandCss'), func_get_args());
            return join("\n", $links);
        }
        return $this->assetHelper->buildBrandCssLink($filename);
    }

    /**
     * Builds HTML for linking to a global Javascript file.
     *
     * @param string $filename,...
     * @return string
     */
    public function globalJs($filename)
    {
        if (func_num_args() > 1) {
            $links = array_map(array($this, 'globalJs'), func_get_args());
            return join("\n", $links);
        }
        return $this->assetHelper->buildGlobalJsLink($filename);
    }
    
    /**
     * Creates HTML for linking to an AppBundle Javascript file.
     *
     * @param string $filename,...
     * @return string
     */
    public function appJs($filename)
    {
        if (func_num_args() > 1) {
            $links = array_map(array($this, 'appJs'), func_get_args());
            return join("\n", $links);
        }
        return $this->assetHelper->buildAppJsLink($filename);
    }

    /**
     * Creates HTML for linking to a GatewayBundle Javascript file.
     *
     * @param string $filename,...
     * @return string
     */
    public function brandJs($filename)
    {
        if (func_num_args() > 1) {
            $links = array_map(array($this, 'brandJs'), func_get_args());
            return join("\n", $links);
        }
        return $this->assetHelper->buildBrandJsLink($filename);
    }

    /**
     * Creates full path for assets
     *
     * @param string $path
     * @return string
     */
    public function appAsset($path)
    {
        return $this->assetHelper->getAppAssetDir() . $path;
    }

    /**
     * Formats the ID attribute for the body tag based on the controller
     * and action.
     *
     * @return string
     */
    public function pageDOMId()
    {
        return $this->controller[0]->currentController() .
            '-' .
            str_replace('Action', '', $this->controller[1]);
    }

    /**
     * Proxy for JavascriptHelper::getTranslations.
     *
     * @return string
     */
    public function jsTranslations()
    {
        return json_encode($this->jsHelper->getTranslations());
    }

    /**
     * Proxy for JavascriptHelper::getValues.
     *
     * @return string
     */
    public function jsValues()
    {
        return json_encode($this->jsHelper->getValues());
    }

    /**
     * Proxy for JavascriptHelper::getRoutes.
     *
     * @return string
     */
    public function jsRoutes()
    {
        return json_encode($this->jsHelper->getRoutes());
    }

    /**
     * Proxy for JavascriptHelper::getPermissions.
     *
     * @return string
     */
    public function jsPermissions()
    {
        return json_encode($this->jsHelper->getPermissions());
    }

    /**
     * Proxy for Runtime::getBrandName.
     * @return string
     */
    public function brandName()
    {
        return $this->brandName;
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
        return $this->localizer->formatDatetime(
            $timestamp,
            $format,
            $locale,
            $timezone
        );
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

    /**
     * Proxy for bool filter
     *
     * @param mixed $value
     * @return string bool ("true"|"false")
     *
     */    
    public function bool($value)
    {
        return $value === false ? "false" : "true";
    }
    
    /**
     * get route url from name in the twig
     *
     * @param string $routeName route name
     * @return string route url
     *
     */
    public function routeUrl($routeName)
    {
        return $this->jsHelper->getRouteUrl($routeName);
    }
    
    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'base';
    }
}