<?php
namespace CTLib\Controller;

use CTLib\Util\Arr;

/**
 * @author: Joe Imhoff, bother me if you have questions, I guess.
 *
 * This class includes the necessary parts to a twig file to include all the
 * necessary JavaScript files, CSS Files, inline JavaScript and the basic DOM
 * elements in order to install and initialize a $.multiautocomplete instrumentation
 * on the front end.
 *
 * EXAMPLE: :
 *   {% dynapart MultiAutoComplete : member_lookup_provider |
 *      id="message_compose_dialog_member" name="toMemberId"
 *      class="lookup" placeholder="member_lookup_placeholder"|trans %}
 *
 * EXPLANATION:
 *   {% dynapart MultiAutoComplete : <route shortcut> | id="<DOMelement ID>"
 *   name="<name for input>" class="<option class>" placeholder="<placeholder text>"|trans %}
 */
class MultiAutoCompleteController extends AutoCompleteController
{

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
            ->addAppJs('autocomplete.multiple.extension.js')
            ->addInlineJs('$("#'.$domId.'").multiautocomplete('.$json.');');
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
            ' />';
    }
}
