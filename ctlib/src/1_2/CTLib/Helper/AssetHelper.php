<?php
namespace CTLib\Helper;

class AssetHelper
{

    protected $runtime;
    protected $request;

    public function __construct($kernel)
    {
        $this->runtime = $kernel->getRuntime();
        $this->request = $kernel->getContainer()->get("request");
    }


    public function buildCssLink($url, $media='all')
    {
        return "<link rel='stylesheet' href='$url' type='text/css' media='$media' />";
    }

    public function buildJsLink($url)
    {
        return "<script type='text/javascript' src='$url'></script>";
    }

    public function buildGlobalCssLink($filename)
    {
        return $this->buildCssLink(
            $this->buildGlobalAssetUrl("/css/$filename")
        );
    }

    public function buildBrandCssLink($filename)
    {
        return $this->buildCssLink(
            $this->buildBrandAssetUrl("/css/$filename")
        );
    }

    public function buildGatewayCssLink($filename)
    {
        return $this->buildBrandCssLink($filename);
    }

    public function buildAppCssLink($filename)
    {
        return $this->buildCssLink(
            $this->buildAppAssetUrl("/css/$filename")
        );
    }

    public function buildGlobalJsLink($filename)
    {
        return $this->buildJsLink(
            $this->buildGlobalAssetUrl("/js/$filename")
        );
    }

    public function buildBrandJsLink($filename)
    {
        return $this->buildJsLink(
            $this->buildBrandAssetUrl("/js/$filename")
        );
    }

    public function buildAppJsLink($filename)
    {
        return $this->buildJsLink(
            $this->buildAppAssetUrl("/js/$filename")
        );
    }

    public function buildExternalJsLink($url)
    {
        return $this->buildJsLink($url);
    }

    public function buildGlobalAssetUrl($path)
    {
        return $this->buildLocalAssetUrl($this->getGlobalAssetDir() . $path);
    }

    public function buildBrandAssetUrl($path)
    {
        return $this->buildLocalAssetUrl($this->getBrandAssetDir() . $path);
    }

    public function buildAppAssetUrl($path)
    {
        return $this->buildLocalAssetUrl($this->getAppAssetDir() . $path);
    }

    protected function buildLocalAssetUrl($path)
    {
        if (! $this->runtime) { return $this->getWebRoot() . $path; }
        return $this->getWebRoot()
            . $path
            . ($this->runtime->isDevelopment() ? '?' . time() : '');
    }

    private function getWebRoot()
    {
        $arr = explode("/", $_SERVER["SCRIPT_NAME"]);
        array_pop($arr);
        return implode("/", $arr);
    }

    protected function getGlobalAssetDir()
    {
        return '/bundles/ctglobal'; 
    }

    public function getAppAssetDir()
    {
        if (!$this->runtime) 
			return '/bundles/hq/';
		if ($this->runtime->getProductId()=='HCTP') 
			return '/bundles/hctp/'.$this->runtime->getAppAssetDir(); 
        return '/bundles/ctapp/' . $this->runtime->getAppAssetDir();
    }

    protected function getBrandAssetDir()
    {
        if (! $this->runtime) { return '/bundles/hq/'; }
        return '/bundles/ctgateway/' . $this->runtime->getBrandAssetDir();
    }

    protected function getGatewayAssetDir()
    {
        return $this->getBrandAssetDir();
    }



}