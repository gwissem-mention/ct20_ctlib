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

    protected $assetHelper;

    /**
     * holds javascript src, inline javascript and css for lazy loading
     * 
     * @var array
     */
    protected $loadCollection;


    public function __construct($assetHelper)
    {
        $this->assetHelper = $assetHelper;
        $this->loadCollection = array(
            "jsInline"  => array(),
            "jsSrc"     => array(),
            "cssSrc"    => array(),
        );
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
     * Adds HTML link to AppBundle Javascript file.
     *
     * @param string $filename,...
     * @return $this
     */
    public function addAppJs($filename)
    {
        $filenames = func_num_args() > 1 ? func_get_args() : array($filename);
        foreach ($filenames AS $filename) {
            $html = $this->assetHelper->buildAppJsLink($filename);
            $this->loadCollection['jsSrc'][$filename] = $html;
        }
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
            $html = $this->assetHelper->buildExternalJsLink($jsUrl);
            $this->loadCollection['jsSrc'][$jsUrl] = $html;
        }
        return $this;
    }

    /**
     * Adds HTML link to global Javascript file.
     *
     * @param string $filename,...
     * @return $this
     */
    public function addGlobalJs($filename)
    {
        $filenames = func_num_args() > 1 ? func_get_args() : array($filename);
        foreach ($filenames AS $filename) {
            $html = $this->assetHelper->buildGlobalJsLink($filename);
            $this->loadCollection['jsSrc'][$filename] = $html;
        }
        return $this;
    }

    /**
     * Adds HTML link to AppBundle CSS file.
     *
     * @param string $filename,...
     * @return $this
     */
    public function addAppCss($filename)
    {
        $filenames = func_num_args() > 1 ? func_get_args() : array($filename);
        foreach ($filenames AS $filename) {
            $html = $this->assetHelper->buildAppCssLink($filename);
            $this->loadCollection['cssSrc'][$filename] = $html;
        }
        return $this;
    }

    /**
     * Adds HTML link to global CSS file.
     *
     * @param string $filename,...
     * @return $this
     */
    public function addGlobalCss($filename)
    {
        $filenames = func_num_args() > 1 ? func_get_args() : array($filename);
        foreach ($filenames AS $filename) {
            $html = $this->assetHelper->buildGlobalCssLink($filename);
            $this->loadCollection['cssSrc'][$filename] = $html;
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
}