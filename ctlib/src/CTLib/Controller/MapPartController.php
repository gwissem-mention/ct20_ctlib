<?php
namespace CTLib\Controller;

use CTLib\Helper\LocalizationHelper;

class MapPartController extends DynaPartController
{
    protected function addDynaPartDependencies($domId, $json, $assetLoader, $jsHelper)
    {
        //put in the map center
        $localizer    = $this->get("localizer");
        $locale       = $this->session()->getLocale();
        $distanceUnit = $localizer->getCountryDistanceUnit();
        list($mapCenterLat, $mapCenterLng) = $localizer->getMapCenter();

        $json->merge(array(
            "center" => array(
                "lat" => $mapCenterLat,
                "lng" => $mapCenterLng
            ),
            "locale" => $locale,
            "unit" => $distanceUnit
        ));

        $assetLoader
            ->addExternalJs("https://www.mapquestapi.com/sdk/js/v7.0.s/mqa.toolkit.js?key=Gmjtd%7Clu6zn1ua2d%2C7s%3Do5-l07g0")
            ->addAppJs("maps.plugin.js")
            ->addAppCss("mapsPois.css")
            ->addInlineJs('$("#'.$domId.'").maps('.$json.');');
        $jsHelper
            ->addConstants(
                "CTLib\Helper\LocalizationHelper", "/^DISTANCE_UNIT/"
            );
    }

    protected function buildDynaPartHtml($domAttributes)
    {
        return '<div ' . $this->compileDomAttributes($domAttributes) . '></div>';
    }
}
