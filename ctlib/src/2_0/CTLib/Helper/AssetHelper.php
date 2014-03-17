<?php
namespace CTLib\Helper;

class AssetHelper
{

    /**
     * @var string
     */
    protected $environment;
    
    /**
     * @var array
     */
    protected $dirs;


    /**
     * @param string $environment
     */
    public function __construct($environment)
    {
        $this->environment  = $environment;
        $this->dirs         = array();
    }

    public function addDirectory($name, $path)
    {
        $this->dirs[strtolower($name)] = $path;
    }

    public function getDirectoryNames()
    {
        return array_keys($this->dirs);
    }

    public function buildCssLink($url, $media='all')
    {
        return "<link rel='stylesheet' href='$url' type='text/css' media='$media' />";
    }

    public function buildJsLink($url)
    {
        return "<script type='text/javascript' src='$url'></script>";
    }

    public function buildLocalAssetUrl($dirName, $path)
    {
        return $this->getWebRoot()
                . $this->buildLocalAssetPath($dirName, $path)
                . ($this->environment == 'dev' ? '?' . time() : '');
    }

    public function buildLocalAssetPath($dirName, $path)
    {
        if (! isset($this->dirs[$dirName])) {
            throw new \Exception("Invalid asset directory '{$dirName}'");
        }
        return "{$this->dirs[$dirName]}/{$path}";
    }

    public function __call($methodName, $args)
    {
        if (preg_match('/^build([a-z]+)(Css|Js)Link$/i', $methodName, $matches)) {
            $dirName    = strtolower($matches[1]);
            $type       = strtolower($matches[2]);
            $filename   = $args[0];
            $path       = "{$type}/{$filename}";
            $url        = $this->buildLocalAssetUrl($dirName, $path);
            
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
            return $this->buildLocalAssetUrl($dirName, $path);
        }

        throw new \Exception(get_class($this) . " does not have method '{$methodName}'");
    }

    public function buildExternalJsLink($url)
    {
        return $this->buildJsLink($url);
    }

    protected function getWebRoot()
    {
        $arr = explode("/", $_SERVER["SCRIPT_NAME"]);
        array_pop($arr);
        return implode("/", $arr);
    }

    


}