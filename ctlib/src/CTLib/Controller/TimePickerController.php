<?php
namespace CTLib\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Controller for Dynapart of DatePicker
 *
 */
class TimePickerController extends DynaPartController
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
        $localizer = $this->localizer();

        $json->merge(array(
            "ampmNames" => $localizer->getLocaleConfigValue("datetime.ampmNames"),
            "format"    => $localizer->getLocaleConfigValue("dateinput.timepickerFormat")
        ));
        //load needed css and javascript lib and script
        $assetLoader
            ->addAppCss('timepicker.css')
            ->addAppJs('timepicker.plugin.js')
            ->addInlineJs('$("#'.$domId.'").timepicker(' . $json . ');');
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
        $class = $domAttributes["class"];
        unset($domAttributes["class"]);
        
        return
        '<div class="timepicker-plugin">' .
            '<input type="text" class="timepicker_input ' . $class . '" ' .
            $this->compileDomAttributes($domAttributes) .
            '/>' .
        '</div>';
    }
}
