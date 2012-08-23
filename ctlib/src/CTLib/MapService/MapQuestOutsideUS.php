<?php
namespace CTLib\MapService;

use CTLib\Util\CTCurl,
    CTLib\Util\Arr;

/**
 * class MapQuestOutsideUS overwrites the behaviors of geocode and reverse geocode, in order to handle
 * different map web service request for mapquest's geocode and reverse geocode. the rest of the functionalities
 * are the same as Mapquest class
 * 
 */
class MapQuestOutsideUS extends Mapquest
{

    protected function geocodeBuildRequest($request, $address, $country = null)
    {
        if (is_string($address)) {
            throw new \exception("Single line address will not be accepted by map sevice outside of US");
        }
        parent::geocodeBuildRequest($request, $address);
    }

    protected function geocodeBatchBuildRequest($request, $addresses, $country = null)
    {
        foreach ($addresses as $address) {
            if (is_string($address)) {
                throw new \exception("Single line address will not be accepted by map sevice outside of US");
            }
        }
        parent::geocodeBatchBuildRequest($request, $addresses);
    }

    protected function reverseGeocodeBuildRequest($request, $latitude, $longitude, $country = null)
    {
        $request->url = "http://open.mapquestapi.com/nominatim/v1/reverse?format=json";
        $request->method = CTCurl::REQUEST_GET;
        $request->data = array(
            "lat" => $latitude,
            "lon" => $longitude
        );
    }

    protected function reverseGeocodeProcessResult($result)
    {
        $decodedResult = json_decode($result, true);
        if (!$decodedResult) {
            throw new \Exception("result is invalid");
        }

        $address = Arr::findByKeyChain($decodedResult, "address");
        if (!$address) {
            throw new \Exception("result is invalid");
        }

        return array(
            "qualityCode" => "L1AAA",
            "street"      => Arr::get("house_number", $address, "") . " " . $address["road"],
            "city"        => Arr::get("city", $address),
            "district"    => Arr::get("county", $address),
            "locality"    => Arr::get("state_district", $address),
            "subdivision" => Arr::get("state", $address),
            "postalCode"  => Arr::get("postcode", $address),
            "country"     => Arr::get("country", $address),
            "mapUrl"      => null,
            "lat"         => Arr::get("lat", $decodedResult),
            "lng"         => Arr::get("lon", $decodedResult)
        );
    }
}