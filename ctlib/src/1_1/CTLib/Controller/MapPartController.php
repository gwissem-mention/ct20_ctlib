<?php
namespace CTLib\Controller;

use CTLib\Helper\LocalizationHelper;

class MapPartController extends DynaPartController
{
    protected function addDynaPartDependencies($domId, $json, $assetLoader, $jsHelper)
    {
        //put in the map center
        $localizer    = $this->get("localizer");
        $locale       = $this->request()->getLocale();
        $distanceUnit = $localizer->getCountryDistanceUnit();
        list($mapCenterLat, $mapCenterLng) = $localizer->getMapCenter();

        $json->merge(array(
            "center" => array(
                "lat" => $mapCenterLat,
                "lng" => $mapCenterLng
            ),
            "zoom" => $localizer->getMapZoom(),
            "locale" => $locale,
            "unit" => $distanceUnit,
            "country" => $localizer->getSessionCountryCode(),
        ));

        $mapService = $this->get("map_service");
        $assetLoader
            ->addExternalJs($mapService->getJavascriptApiUrl())
            ->addAppJs($mapService->getJavascriptMapPlugin())
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
