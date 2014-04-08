<?php
namespace CTLib\Helper;

/**
 * Facilitates use of assets (images, CSS, JS) in views.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class AssetHelper
{

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var Request
     */
    protected $request;
    
    /**
     * @var array
     */
    protected $dirs;


    /**
     * @param string $environment
     * @param Request $request
     */
    public function __construct($environment, $request)
    {
        $this->environment  = $environment;
        $this->request      = $request;
        $this->dirs         = [];
    }

    /**
     * Adds supported asset directory.
     *
     * @param string $name
     * @param string $path
     *
     * @return void
     */
    public function addDirectory($name, $path)
    {
        $this->dirs[strtolower($name)] = $path;
    }

    /**
     * Returns names of supported asset directories.
     *
     * @return array
     */
    public function getDirectoryNames()
    {
        return array_keys($this->dirs);
    }

    /**
     * Builds HTML tag for a CSS link.
     *
     * @param string $url
     * @param string $media
     *
     * @return string
     */
    public function buildCssLink($url, $media='all')
    {
        return "<link rel='stylesheet' href='$url' type='text/css' media='$media' />";
    }

    /**
     * Builds HTML tag for a Javascript link.
     *
     * @param string $url
     * @return string
     */
    public function buildJsLink($url)
    {
        return "<script type='text/javascript' src='$url'></script>";
    }

    /**
     * Builds relative URL to asset.
     *
     * @param string $dirName
     * @param string $path
     *
     * @return string
     */
    public function buildLocalAssetRelativeUrl($dirName, $path)
    {
        return $this->getWebRoot()
               . $this->buildLocalAssetPath($dirName, $path)
               . ($this->environment == 'dev' ? '?' . time() : '');
    }

    /**
     * Builds absolute URL to asset.
     *
     * @param string $dirName
     * @param string $path
     *
     * @return string
     */
    public function buildLocalAssetAbsoluteUrl($dirName, $path)
    {
        return $this->getHttpProtocol()
               . $this->request->server->get('HTTP_HOST')
               . $this->buildLocalAssetRelativeUrl($dirName, $path);
    }

    /**
     * Builds path to asset.
     *
     * @param string $dirName
     * @param string $path
     *
     * @return string
     */
    public function buildLocalAssetPath($dirName, $path)
    {
        if (! isset($this->dirs[$dirName])) {
            throw new \Exception("Invalid asset directory '{$dirName}'");
        }
        return "{$this->dirs[$dirName]}/{$path}";
    }

    /**
     * Supports:
     *
     *  build{dirName}CssLink($path)
     *  build{dirName}JsLink($path)
     *  build{dirName}Path($path)
     *  build{dirName}AbsoluteUrl($path)
     */
    public function __call($methodName, $args)
    {
        if (preg_match('/^build([a-z]+)(Css|Js)Link$/i', $methodName, $matches)) {
            $dirName    = strtolower($matches[1]);
            $type       = strtolower($matches[2]);
            $filename   = $args[0];
            $path       = "{$type}/{$filename}";
            $url        = $this->buildLocalAssetRelativeUrl($dirName, $path);
            
            if ($type == 'css') {
                $media = isset($args[1]) ? $args[1] : 'all';
                return $this->buildCssLink($url, $media);
            } else {
                return $this->buildJsLink($url);
            }
        }

        if (preg_match('/^build([a-z]+)Path$/i', $methodName, $matches)) {
            $dirName    = strtolower($matches[1]);
            $path       = $args[0];
            return $this->buildLocalAssetPath($dirName, $path);
        }

        if (preg_match('/^build([a-z]+)AbsoluteUrl$/i', $methodName, $matches)) {
            $dirName    = strtolower($matches[1]);
            $path       = $args[0];
            return $this->buildLocalAssetAbsoluteUrl($dirName, $path, true);
        }

        throw new \Exception(get_class($this) . " does not have method '{$methodName}'");
    }

    /**
     * Builds HTML tag for an external Javascript link.
     *
     * @param string $url
     * @return string
     */
    public function buildExternalJsLink($url)
    {
        return $this->buildJsLink($url);
    }

    /**
     * Returns web root.
     *
     * @return string
     */
    protected function getWebRoot()
    {
        $arr = explode("/", $this->request->server->get('SCRIPT_NAME'));
        array_pop($arr);
        return implode("/", $arr);
    }

    /**
     * Returns HTTP protocol in use.
     *
     * @return string
     */
    protected function getHttpProtocol()
    {
        $protocol = $this->request->server->get('SERVER_PROTOCOL');
        return strpos($protocol, 'HTTPS') !== false ? 'https://' : 'http://';
    }

}