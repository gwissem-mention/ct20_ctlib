<?php
namespace CTLib\Controller;

use CTLib\Util\Arr;


class AutoCompleteController extends DynaPartController
{
    protected function getDynaPartCachedOptions($id, $domId)
    {
        return array();
    }

    protected function addDynaPartDependencies($domId, $json, $assetLoader, $jsHelper)
    {
        $assetLoader
            ->addAppJs('autocomplete.plugin.js')
            ->addInlineJs('$("#'.$domId.'").autocomplete('.$json.');')
            ->addAppCss('autocomplete.css');
    }

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