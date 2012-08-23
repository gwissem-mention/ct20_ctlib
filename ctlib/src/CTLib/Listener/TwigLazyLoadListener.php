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
            || $this->isLoadCollectionEmpty()) {
            // Nothing to do.
            return;
        }

        // Inject lazy loaded components into response content.
        $content = $event->getResponse()->getContent();
        $content = $this->injectJavascript($content);
        $content = $this->injectCss($content);
        $event->getResponse()->setContent($content);
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
     * Indicates whether any lazy load component has been added.
     *
     * @return boolean
     */
    protected function isLoadCollectionEmpty()
    {
        return ! $this->loadCollection['jsInline']
            && ! $this->loadCollection['jsSrc']
            && ! $this->loadCollection['cssSrc'];
    }

    /**
     * Injects lazy-loaded Javascript code into response.
     *
     * @param string $content   Response content.
     * @return string
     */
    protected function injectJavascript($content)
    {
        if (! $this->loadCollection['jsSrc']
            && ! $this->loadCollection['jsInline']) {
            return $content;
        }

        $this->removeDuplicateJavascriptSrc($content);

        // Compile Javascript code and inject it.
        $js = join("\n", $this->loadCollection['jsSrc']);

        if ($this->loadCollection['jsInline']) {
            $js .= "\n<script type='text/javascript'>"
                . join("\n\n", $this->loadCollection['jsInline'])
                . "</script>";
        }

        return $this->injectContent(
            $content, 
            '<script id="jsLazyLoadPlaceholder"></script>',
            $js,
            true
        );
    }

    /**
     * Injects lazy-loaded CSS code into response.
     *
     * @param string $content   Response content.
     * @return string
     */
    protected function injectCss($content)
    {
        if (! $this->loadCollection['cssSrc']) { return $content; }

        // Compile CSS code and inject it.
        $css = join("\n", $this->loadCollection['cssSrc']);
        return $this->injectContent($content, '</head>', $css);
    }

    /**
     * Injects HTML code into response content.
     *
     * @param string $content   Response content.
     * @param int $position     Start position for injection.
     * @param string $injection HTML to inject.
     *
     * @return string
     */
    protected function injectContent($content, $placeholderTag, $injection, $isPlacehostReplaced = false)
    {
        $placeholderTag = trim($placeholderTag);
        $injection = trim($injection);

        if (!$placeholderTag || !$injection) { return; }

        $position = $this->getTagPosition($content, $placeholderTag);

        if ($position === false) {
            throw new \Exception("Page must have {$placeholderTag} tag.");
        }

        return mb_substr($content, 0, $position)
            . "\n" . $injection . "\n"
            . mb_substr($content, $position + ($isPlacehostReplaced ? strlen($placeholderTag) : 0) );
    }

    /**
     * Returns position of HTML tag in response content.
     *
     * @param string $content   Response content.
     * @param string $tag       HTML tag to find.
     *
     * @return mixed            Return int or FALSE if $tag not found.
     */
    protected function getTagPosition($content, $tag)
    {
        return mb_strripos($content, $tag);
    }

    /**
     * Remove duplicated javascript src loading from loadCollection['jsSrc']
     *
     * @param string $content page content
     * @return void
     *
     */
    protected function removeDuplicateJavascriptSrc($content)
    {
        $pos = mb_strripos($content, "router.js");
        $content = mb_substr($content, $pos);

        $isMatched = preg_match_all(
            "/<script.*src=['\"]\/?(.*\/)*([\w.]*)?.*['\"]\s*>/i",
            $content,
            $match
        );
        
        if (!$isMatched) return;

        foreach ($this->loadCollection['jsSrc'] as $name => $jsSrc) {
            if (in_array($name, $match[2])) {
                unset($this->loadCollection['jsSrc'][$name]);
            }
        }
    }
}