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
        $localizer = $this->localizer();

        //load needed css and javascript lib and script
        $json->merge(array(
            "changeMonth"     => true,
            "changeYear"      => true,
            "dayNames"        => $localizer->getLocaleConfigValue("datetime.dateNames"),
            "dayNamesMin"     => $localizer->getLocaleConfigValue("datetime.dayNamesMin"),
            "dayNamesShort"   => $localizer->getLocaleConfigValue("datetime.dayNamesShort"),
            "monthNames"      => $localizer->getLocaleConfigValue("datetime.monthNames"),
            "monthNamesShort" => $localizer->getLocaleConfigValue("datetime.monthNamesShort"),
            "firstDay"        => $localizer->getCountryConfigValue("datetime.firstDayInWeek"),
            "dateFormat"      => $localizer->getLocaleConfigValue("dateinput.dateFormat")
        ));

        $assetLoader
            ->addAppCss('datepicker.css')
            ->addInlineJs('$("#'.$domId.'").datepicker(' . $json . ');');
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
