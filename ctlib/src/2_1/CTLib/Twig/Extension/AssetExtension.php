<?php
namespace CTLib\Twig\Extension;


class AssetExtension extends \Twig_Extension
{
    
    /**
     * @var RouteInspector
     */
    protected $assetHelper;


    /**
     * @param AssetHelper $assetHelper
     */
    public function __construct($assetHelper)
    {
        $this->assetHelper = $assetHelper;
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
        $assetHelper    = $this->assetHelper;
        $functions      = array();

        foreach ($assetHelper->getDirectoryNames() as $dirName) {
            // Add CSS method.
            $methodName = "{$dirName}Css";
            $functions[$methodName] = new \Twig_Function_Method($this, $methodName);

            // Add JS method.
            $methodName = "{$dirName}Js";
            $functions[$methodName] = new \Twig_Function_Method($this, $methodName);

            // Add path method.
            $methodName = "{$dirName}Asset";
            $functions[$methodName] = new \Twig_Function_Method($this, $methodName);
        }
        return $functions;
    }

    public function __call($methodName, $args)
    {
        if (preg_match('/^([a-z]+)(Js|Css)$/i', $methodName, $matches)) {
            $dirName    = strtolower($matches[1]);
            $type       = $matches[2];
            $links      = array();
            $methodName = "build{$dirName}{$type}Link";

            foreach ($args as $filename) {
                $links[] = $this->assetHelper->{$methodName}($filename);
            }
            return join("\n", $links);
        }

        if (preg_match('/^([a-z]+)Asset$/i', $methodName, $matches)) {
            $dirName    = strtolower($matches[1]);
            $path       = $args[0];
            return $this->assetHelper->buildLocalAssetPath($dirName, $path);
        }


        throw new \Exception(get_class($this) . " does not have method '{$methodName}'");

    }

    
}