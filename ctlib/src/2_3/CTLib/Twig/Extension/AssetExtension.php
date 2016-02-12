<?php
namespace CTLib\Twig\Extension;


/**
 * Streamlines creation of links to assets.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class AssetExtension extends \Twig_Extension
{
    
    /**
     * @var UrlHelper
     */
    protected $urlHelper;


    /**
     * @param UrlHelper $urlHelper
     * @param string $environment
     */
    public function __construct($urlHelper, $environment)
    {
        $this->urlHelper    = $urlHelper;
        $this->environment  = $environment;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'asset';
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        $functions = [];

        foreach ($this->urlHelper->getNamespaces() as $namespace) {
            // Add CSS method.
            $methodName = "{$namespace}Css";
            $functions[$methodName] = new \Twig_Function_Method($this, $methodName);

            // Add JS method.
            $methodName = "{$namespace}Js";
            $functions[$methodName] = new \Twig_Function_Method($this, $methodName);

            // Add path method.
            $methodName = "{$namespace}Asset";
            $functions[$methodName] = new \Twig_Function_Method($this, $methodName);

            // Add absolute URL method.
            $methodName = "{$namespace}AssetAbsolute";
            $functions[$methodName] = new \Twig_Function_Method($this, $methodName);
        }
        return $functions;
    }

    /**
     * Works in coordination with dynamic nature of getFunctions.
     * Supports:
     *
     *  {namespace}Js($filename, ...)
     *  {namespace}Css($filename, ...)
     *  {namespace}Asset($path, ...)
     *  {namespace}AssetAbsolute($path, ...)
     */
    public function __call($methodName, $args)
    {
        if (preg_match('/^(.+)Js$/i', $methodName, $matches)) {
            $namespace  = $matches[1];
            $links      = [];
            foreach ($args as $filename) {
                $url        = $this->getRelativeJsUrl($namespace, $filename);
                $links[]    = $this->buildJsLink($url);
            }
            return join("\n", $links);
        }

        if (preg_match('/^(.+)Css$/i', $methodName, $matches)) {
            $namespace  = $matches[1];
            $links      = [];
            foreach ($args as $filename) {
                $url        = $this->getRelativeCssUrl($namespace, $filename);
                $links[]    = $this->buildCssLink($url);
            }
            return join("\n", $links);
        }

        if (preg_match('/^([a-z]+)Asset$/i', $methodName, $matches)) {
            $namespace  = $matches[1];
            $path       = $args[0];
            return $this->urlHelper->getRelativeAssetUrl($namespace, $path);
        }

        if (preg_match('/^([a-z]+)AssetAbsolute$/i', $methodName, $matches)) {
            $namespace  = $matches[1];
            $path       = $args[0];
            return $this->urlHelper->getAbsoluteAssetUrl($namespace, $path);
        }

        throw new \Exception(get_class($this) . " does not have method '{$methodName}'");

    }

    /**
     * Returns relative URL to JS asset.
     *
     * @param string $namespace
     * @param string $filename
     *
     * @return string
     */
    public function getRelativeJsUrl($namespace, $filename)
    {
        $path   = "js/{$filename}";
        $url    = $this->urlHelper->getRelativeAssetUrl($namespace, $path);

        if ($this->environment == 'dev') {
            $url .= '?' . time();
        }
        return $url;
    }

    /**
     * Returns relative URL to CSS asset.
     *
     * @param string $namespace
     * @param string $filename
     *
     * @return string
     */
    public function getRelativeCssUrl($namespace, $filename)
    {
        $path   = "css/{$filename}";
        $url    = $this->urlHelper->getRelativeAssetUrl($namespace, $path);

        if ($this->environment == 'dev') {
            $url .= '?' . time();
        }
        return $url;
    }

    /**
     * Builds HTML for JS link.
     *
     * @param string $url
     * @return string
     */
    public function buildJsLink($url)
    {
        return "<script type='text/javascript' src='$url'></script>";
    }

    /**
     * Builds HTML for CSS link.
     *
     * @param string $url
     * @return string
     */
    public function buildCssLink($url, $media='all')
    {
        return "<link rel='stylesheet' href='$url' type='text/css' media='$media' />";
    }

}