<?php
namespace CTLib\Controller;

use CTLib\Util\Arr;


class AutoCompleteController extends DynaPartController
{
    /**
     * Returns cached JavaScript configuration options.
     *
     * @param string $cacheId    Unique ID of DynaPart.
     * @param string $domId configured in dom attribute
     * @return array
     */
    protected function getDynaPartCachedOptions($id, $domId)
    {
        return array();
    }

    /**
     * Adds assets and JS pass-thrus required by DynaPart.
     *
     * @param string $domId
     * @param string $json      JSON configuration options.
     * @param TwigLazyLoaderListener $assetLoader
     * @param JsHelper $jsHelper
     *
     * @return void
     */
    protected function addDynaPartDependencies($domId, $json, $assetLoader, $jsHelper)
    {
        $assetLoader
            ->addAppJs('autocomplete.plugin.js')
            ->addInlineJs('$("#'.$domId.'").autocomplete('.$json.');');
    }

    /**
     * Builds HTML GUI for DynaPart.
     *
     * @param array $domAttributes      As array($attribute => $value).
     * @return string
     */
    protected function buildDynaPartHtml($domAttributes)
    {
        $domId = Arr::mustGet('id', $domAttributes);
        $domName = Arr::mustGet('name', $domAttributes);

        $domAttributes['name'] = $domName . '_autocomplete';

        if (! isset($domAttributes['class'])) {
            $domAttributes['class'] = '';
        }

        $isCacheOnly = strpos($domAttributes['class'], "cache_only") !== false;
        if (!$isCacheOnly) {
            $domAttributes['class'] .= ' cache_only';
        }

        return
            '<input type="text" ' .
            $this->compileDomAttributes($domAttributes) .
            ' />' .
            '<input type="hidden" name="'. $domName .'" ' . ($isCacheOnly?'class="cache_only"':'') . '/>';
    }



}
