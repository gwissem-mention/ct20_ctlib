<?php
namespace CTLib\Listener;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent,
    Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * This class handles the lazy loading for css and javascript.
 * Controllers can assign css and js to this listener.
 * This listener will wait until the main body response gets generated, 
 * then put javascript on the bottom of the page, and css inside of header.
 * Controllers can get this listener by using
 * $this->container->get('twig.lazyload.listener').
 */
class TwigLazyLoadListener
{

    /**
     * @var AssetExtension
     */
    protected $assetExtension;

    /**
     * holds javascript src, inline javascript and css for lazy loading
     * 
     * @var array
     */
    protected $loadCollection;


    /**
     * @param AssetExtension $assetExtension
     */
    public function __construct($assetExtension)
    {
        $this->assetExtension = $assetExtension;
        $this->loadCollection = [
            "jsInline"  => [],
            "jsSrc"     => [],
            "cssSrc"    => [],
        ];
    }

    /**
     * Handles lazy loading of js and css when master response is sent out.
     *
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()
            || $event->getRequest()->isXmlHttpRequest()
            || ! $this->loadCollection['cssSrc']) {
            // Nothing to do.
            return;
        }

        // Inject lazy CSS into response.
        $content    = $event->getResponse()->getContent();
        $position   = mb_strpos($content, '<head>');
        $position   += 6; // length of <head>

        if ($position) {
            $content = mb_substr($content, 0, $position)
                     . "\n" . join("\n", $this->loadCollection['cssSrc']) . "\n"
                     . mb_substr($content, $position);
            $event->getResponse()->setContent($content);
        }
    }

    /**
     * Adds inline Javascript code.
     *
     * @param string $js
     * @return $this
     */
    public function addInlineJs($js)
    {
        $this->loadCollection["jsInline"][] = $js;
        return $this;
    }

    /**
     * Add external js library
     *
     * @param string $url, ...
     * @return $this
     *
     */    
    public function addExternalJs($url)
    {
        $urls = func_num_args() > 1 ? func_get_args() : array($url);
        foreach ($urls as $jsUrl) {
            $html = $this->assetExtension->buildJsLink($jsUrl);
            $this->loadCollection['jsSrc'][$jsUrl] = $html;
        }
        return $this;
    }

    /**
     * Returns lazy-loaded Javascript content.
     *
     * @return string
     */
    public function getJavascript()
    {
        return join("\n", $this->loadCollection['jsSrc'])
                . "\n<script type='text/javascript'>"
                . "\n" . join("\n\n", $this->loadCollection['jsInline'])
                . "</script>";
    }

    /**
     * Ties into dynamic nature of UrlHelper+AssetExtension.
     * Supports:
     *
     *  add{Namespace}Js($filename,...)
     *  add{Namespace}Css($filename,...)
     */
    public function __call($methodName, $args)
    {
        if (preg_match('/^add(.+)Js$/i', $methodName, $matches)) {
            $namespace = $matches[1];
            foreach ($args as $filename) {
                $this->addJsLink($namespace, $filename);
            }
            return $this;
        }

        if (preg_match('/^add(.+)Css$/i', $methodName, $matches)) {
            $namespace = $matches[1];
            foreach ($args as $filename) {
                $this->addCssLink($namespace, $filename);
            }
            return $this;
        }

        throw new \Exception(get_class($this) . " does not have method '{$methodName}'");
    }

    /**
     * Adds JS link to be lazy loaded.
     *
     * @param string $namespace
     * @param string $filename
     *
     * @return void
     */
    protected function addJsLink($namespace, $filename)
    {
        $url = $this
                ->assetExtension
                ->getRelativeJsUrl($namespace, $filename);
        $link = $this->assetExtension->buildJsLink($url);
        $this->loadCollection['jsSrc'][$filename] = $link;
    }

    /**
     * Adds CSS link to be lazy loaded.
     *
     * @param string $namespace
     * @param string $filename
     *
     * @return void
     */
    protected function addCssLink($namespace, $filename)
    {
        $url = $this
                ->assetExtension
                ->getRelativeCssUrl($namespace, $filename);
        $link = $this->assetExtension->buildCssLink($url);
        $this->loadCollection['cssSrc'][$filename] = $link;
    }
    
}