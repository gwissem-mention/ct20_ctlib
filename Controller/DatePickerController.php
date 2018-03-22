<?php
namespace CTLib\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Controller for Dynapart of DatePicker
 *
 */
class DatePickerController extends DynaPartController
{

    /**
     * Add dependencies for DynaPart
     *
     * @param string                 $domId
     * @param string                 $json
     * @param TwigLazyLoaderListener $assetLoader
     * @param JsHelper               $jsHelper
     *
     * @return void
     */
    protected function addDynaPartDependencies($domId, $json, $assetLoader, $jsHelper)
    {
        $json->merge($this->localizer()->getDatePickerConfig());

        $assetLoader->addInlineJs('$("#'.$domId.'").datepicker(' . $json . ');');
    }

    /**
     * Create the html for the DynaPart.
     *
     * @param array $domAttributes
     *
     * @return string
     */
    protected function buildDynaPartHtml($domAttributes)
    {
        return
            '<div class="datepicker-plugin">' .
                '<input type="text" ' .
                    $this->compileDomAttributes($domAttributes) .
                '/>' .
            '</div>';
    }
}
