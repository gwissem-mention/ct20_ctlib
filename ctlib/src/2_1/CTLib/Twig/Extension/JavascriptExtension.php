<?php
namespace CTLib\Twig\Extension;


class JavascriptExtension extends \Twig_Extension
{
    
    /**
     * @var JavscriptHelper
     */
    protected $jsHelper;

    /**
     * @var TwigLazyLoadListener
     */
    protected $lazyLoader;

    /**
     * @param JavascriptHelper $jsHelper
     * @param TwigLazyLoadListener $lazyLoader
     */
    public function __construct($jsHelper, $lazyLoader=null)
    {
        $this->jsHelper     = $jsHelper;
        $this->lazyLoader   = $lazyLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'js';
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            'jsTranslations' => new \Twig_Function_Method($this, 'jsTranslations'),
            'jsValues'       => new \Twig_Function_Method($this, 'jsValues'),
            'jsRoutes'       => new \Twig_Function_Method($this, 'jsRoutes'),
            'jsPermissions'  => new \Twig_Function_Method($this, 'jsPermissions'),
            'lazyJs'         => new \Twig_Function_Method($this, 'lazyJs'),
        );
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
     * Returns lazy-loaded Javascript content.
     *
     * @return string
     */
    public function lazyJs()
    {
        return $this->lazyLoader ? $this->lazyLoader->getJavascript() : '';
    }


    
}