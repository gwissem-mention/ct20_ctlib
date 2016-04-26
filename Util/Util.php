<?php
namespace CTLib\Util;

use CTLib\Helper\LocalizationHelper;

/**
 * Helper utility methods with no better home.
 */
class Util
{

    /**
     * Indicates whether $str starts with $prefix.
     *
     * @param string $str
     * @param string $prefix
     *
     * @return boolean
     */
    public static function startsWith($str, $prefix)
    {
        return strpos($str, $prefix) === 0;
    }

    /**
     * Indicates whether $str ends with $suffix.
     *
     * @param string $str
     * @param string $suffix
     *
     * @return boolean
     */
    public static function endsWith($str, $suffix)
    {
        return substr($str, -strlen($suffix)) === $suffix;
    }

    /**
     * Appends $suffix to $str if not already present.
     *
     * @param string $str
     * @param string $suffix
     *
     * @return string
     */
    public static function append($str, $suffix)
    {
        if (! self::endsWith($str, $suffix)) {
            $str .= $suffix;
        }
        return $str;
    }

    /**
     * Prepends $prefix to $str if not already present.
     *
     * @param string $str
     * @param string $prefix
     *
     * @return string
     */
    public static function prepend($str, $prefix)
    {
        if (! self::startsWith($str, $prefix)) {
            $str = $prefix . $str;
        }
        return $str;
    }

    /**
     * Encodes values in JSON and prints to buffer.
     *
     * Intention is to serve as a replacement for var_dump in service responses
     * because Xdebug will format var_dump output with HTML.
     *
     * @param mixed $val ,...
     *
     * @return void
     */
    public static function jsonDump($val)
    {
        print(json_encode(func_get_args()));
    }

    /**
     * Converts milliseconds to seconds.
     *
     * @param integer $milliseconds
     *
     * @return integer
     */
    public static function millisecsToSecs($milliseconds)
    {
        return (int) floor($milliseconds / 1000);
    }

    /**
     * Converts seconds to milliseconds.
     *
     * @param integer $secs
     *
     * @return integer
     */
    public static function secsToMillisecs($secs)
    {
        return $secs * 1000;
    }

    /**
     * Converts seconds to minutes.
     *
     * @param integer $seconds
     *
     * @return integer
     */
    public static function secsToMins($seconds)
    {
        return (int) floor($seconds / 60);
    }

    /**
     * Convert and round duration from secs into minutes
     *
     * @param integer $startTime start time.
     * @param integer $stopTime stop time.
     *
     * @return integer
     */
    public function calcDurationInMins($startTime, $stopTime)
    {
        return floor($stopTime / 60) - floor($startTime / 60);
    }

    /**
     * Converts meteres to kilometers.
     *
     * @param float|integer $meters
     * @return float
     */
    public static function metersToKilometers($meters)
    {
        return $meters * .001;
    }

    /**
     * Converts meters to miles.
     *
     * @param float|integer $meters
     * @return float
     */
    public static function metersToMiles($meters)
    {
        return $meters * .000621371;
    }

    /**
     * Converts kilometers to meters.
     *
     * @param float|integer $kilometers
     * @return float
     */
    public static function kilometersToMeters($kilometers)
    {
        return $kilometers / .001;
    }

    /**
     * Converts miles to meters.
     *
     * @param float|integer $miles
     * @return float
     */
    public static function milesToMeters($miles)
    {
        return $miles / .000621371;
    }

    /**
     * Returns class name without namespacing.
     *
     * @param mixed $object
     * @return string
     */
    public static function shortClassName($object)
    {
        $className      = is_string($object) ? $object : get_class($object);
        $classTokens    = explode('\\', $className);
        return end($classTokens);
    }

    /**
     * Get difference in latitude and longitude for distance of radius in distance unit
     *
     * @param float $centerLat     Center point's latitude
     * @param float $centerLng     Center point's longitude
     * @param float $radius        Distance
     * @param string $distanceUnit Distance unit, can only be
     *          LocalizationHelper::DISTANCE_UNIT_MILE or
     *          LocalizationHelper::DISTANCE_UNIT_KILOMETER
     *
     * @return array array(difference in latitude, difference in longitude)
     */
    public static function getLatLngDelta($centerLat, $centerLng, $radius, $distanceUnit)
    {
        if ($distanceUnit == LocalizationHelper::DISTANCE_UNIT_MILE) {
            $R = 69.172;
        }
        else if ($distanceUnit == LocalizationHelper::DISTANCE_UNIT_KILOMETER) {
            $R = 111.321;
        }
        else {
            throw new \Exception("unit is invalid");
        }

        //get delta in lat & lng
        return array(
            $radius / $R,
            abs($radius / (cos(deg2rad($centerLat)) * $R))
        );
    }

    /**
     * Is an email address valid?
     *
     * @param string  $data
     * @param boolean $strict
     *
     * @return array|false
     */
    public static function emailIsValid($data, $strict = false)
    {
        $regex = $strict?
            '/^([.0-9a-z_-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,4})$/i' :
            '/^([*+!.&#$¦\'\\%\/0-9a-z^_`{}=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,4})$/i'
            ;
        if (preg_match($regex, trim($data), $matches)) {
            // Format checks out, validate domain name
            return checkdnsrr($matches[2]);
        } else {
            return false;
        }
    }

    /**
     * Copies object but rather than use clone which maintains references, all
     * properties are copied by value.
     *
     * @param mixed $object
     * @return mixed
     */
    public static function deepCopy($object)
    {
        return unserialize(serialize($object));
    }

    /**
     * Save Binary data into Temp file
     *
     * @param mixed $binary any binary data
     * @param string $suffix suffix of temp file
     * @return string temp file name
     *
     */
    public static function saveBinaryToTemp($binary, $suffix="CTI")
    {
        $fileName = tempnam(sys_get_temp_dir(), $suffix);

        file_put_contents($fileName, $binary);

        return $fileName;
    }

    /**
     * Converts string to boolean.
     * NOTE: This method is not intended to replace PHP's built-in string to
     * boolean value cast. Instead, its purpose to is to convert common strings
     * to their boolean counterpart (e.g., "y" => true).
     * @param  string $str
     * @return boolean
     */
    public static function strToBool($str)
    {
        $str = strtolower($str);

        if (in_array($str, ['1', 'true', 'y'])) {
            return true;
        } elseif (in_array($str, ['0', 'false', 'n'])) {
            return false;
        } else {
            return null;
        }
    }

    /**
     * Converts PHP ini memory string to bytes.
     * @param  string $str 
     * @return integer      
     */
    public static function iniStrToBytes($str)
    {
        $unit   = strtolower(substr($str, -1));
        $value  = substr($str, 0, -1);

        switch($unit) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
