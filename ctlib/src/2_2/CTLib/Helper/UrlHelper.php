<?php
namespace CTLib\Helper;

use CTLib\Util\Util;


/**
 * Manages use of configured URLs.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class UrlHelper
{
    
    /**
     * @var array
     */
    protected $urls;

    
    public function __construct()
    {
        $this->urls = [];
    }

    /**
     * Adds configured URL.
     *
     * @param string $namespace Unique identifier for URL.
     * @param string $host      Host including protocol (i.e., https://myhost.com)
     * @param string $assetPath Path to assets (if applicable)
     *
     * @return void
     */
    public function addUrl($namespace, $host, $assetPath=null)
    {
        $url = new \stdClass;
        $url->host = $host;
        $url->assetPath = $assetPath;

        $this->urls[strtolower($namespace)] = $url;
    }

    /**
     * Returns namespaces of configured URLs.
     *
     * @return array
     */
    public function getNamespaces()
    {
        return array_keys($this->urls);
    }

    /**
     * Returns relative URL to $path.
     *
     * @param string $namespace
     * @param string $path
     *
     * @return string
     */
    public function getRelativeUrl($namespace, $path)
    {
        $path = Util::prepend($path, '/');
        return $this->getWebRoot() . $path;
    }

    /**
     * Returns relative URL to asset $path.
     *
     * @param string $namespace
     * @param string $path
     *
     * @return string
     */
    public function getRelativeAssetUrl($namespace, $path)
    {
        $path   = Util::prepend($path, '/');
        $urlCnf = $this->getUrlConfig($namespace);
        return $this->getWebRoot() . $urlCnf->assetPath . $path;
    }

    /**
     * Returns absolute URL to $path.
     *
     * @param string $namespace
     * @param string $path
     *
     * @return string
     */
    public function getAbsoluteUrl($namespace, $path)
    {
        $path   = Util::prepend($path, '/');
        $urlCnf = $this->getUrlConfig($namespace);
        return $urlCnf->host . $path;
    }


    /**
     * Returns absolute URL to asset $path.
     *
     * @param string $namespace
     * @param string $path
     *
     * @return string
     */
    public function getAbsoluteAssetUrl($namespace, $path)
    {
        $path   = Util::prepend($path, '/');
        $urlCnf = $this->getUrlConfig($namespace);
        return $urlCnf->host . $urlCnf->assetPath . $path;
    }

    /**
     * Supports:
     *
     *  getRelative{Namespace}Url($path)
     *  getRelative{Namespace}AssetUrl($path)
     *  getAbsolute{Namespace}Url($path)
     *  getAbsolute{Namespace}AssetUrl($path)
     */
    public function __call($methodName, $args)
    {
        if (preg_match('/^getRelative(.+)Url$/i', $methodName, $matches)) {
            return $this->getRelativeUrl($matches[1], $args[0]);
        }

        if (preg_match('/^getRelative(.+)AssetUrl$/i', $methodName, $matches)) {
            return $this->getRelativeAssetUrl($matches[1], $args[0]);
        }

        if (preg_match('/^getAbsolute(.+)Url$/i', $methodName, $matches)) {
            return $this->getAbsoluteUrl($matches[1], $args[0]);
        }

        if (preg_match('/^getAbsolute(.+)AssetUrl$/i', $methodName, $matches)) {
            return $this->getAbsoluteAssetUrl($matches[1], $args[0]);
        }

        throw new \Exception("Method '{$methodName}' does not exist for " . get_class($this));       
    }

    /**
     * Returns URL configuration.
     *
     * @param string $namespace
     *
     * @return stdObject
     * @throws Exception If URL not configured for $namespace
     */
    protected function getUrlConfig($namespace)
    {
        $namespace = strtolower($namespace);
        if (! isset($this->urls[$namespace])) {
            throw new \Exception("URL not defined for '{$namespace}'");
        }
        return $this->urls[$namespace];
    }

    /**
     * Returns web root.
     *
     * @return string
     */
    protected function getWebRoot()
    {
        $arr = explode("/", $_SERVER['SCRIPT_NAME']);
        array_pop($arr);
        return implode("/", $arr);
    }


}