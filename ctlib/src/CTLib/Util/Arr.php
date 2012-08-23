<?php
namespace CTLib\Util;

/**
 * Additional functions for Arrays.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class Arr
{

    /**
     * Returns value set for $search[$key]. If $key not set, returns $default.
     *
     * @param string $key
     * @param array $search
     * @param mixed $default
     *
     * @return mixed
     */
    public static function get($key, array $search, $default=null)
    {
        return isset($search[$key]) ? $search[$key] : $default;
    }

    /**
     * Works like Arr::get but throws Exception if $key not set.
     *
     * @param string $key
     * @param array $search
     *
     * @return mixed
     */
    public static function mustGet($key, array $search)
    {
        if (! isset($search[$key])) {
            throw new \Exception("Key '{$key}' not set in array");
        }
        return $search[$key];
    }

    /**
     * Works like Arr::get but unsets $key after getting value.
     *
     * @param string $key
     * @param array &$search
     * @param mixed $default
     *
     * @return mixed
     */
    public static function extract($key, array &$search, $default=null)
    {
        $value = self::get($key, $search, $default);
        unset($search[$key]);
        return $value;
    }

    /**
     * Works like Arr::mustGet but unsets $key after getting value.
     *
     * @param string $key
     * @param array &$search
     *
     * @return mixed
     */
    public static function mustExtract($key, array &$search)
    {
        $value = self::mustGet($key, $search);
        unset($search[$key]);
        return $value;
    }

    /**
     * Finds first key/value pair in array that returns TRUE when pair is passed
     * to $callback.
     *
     * @param array     $search
     * @param callable  $callback    function($value, $key) { ... }
     * @param mixed     $default
     *
     * @return array    If match found, returns array($key, $value).
     *                  Otherwise returns empty $default.
     */
    public static function find(array $search, $callback, $default=array())
    {
        foreach ($search as $key => $value) {
            if (call_user_func($callback, $value, $key)) {
                return array($key, $value);
            }
        }
        return $default;
    }

    /**
     * Works like Arr::find but throws Exception if matching pair not found.
     *
     * @param array     $search
     * @param callable  $callback   function($value, $key) { ... }
     *
     * @return array
     * @throws Exception    If match not found.
     */
    public static function mustFind(array $search, $callback)
    {
        $result = self::find($search, $callback);
        if (! $result) {
            throw new \Exception("Matching value not found");
        }

        return $result;
    }

    /**
     * Finds all key/value pairs in array that return TRUE when passed to $callback.
     *
     * @param array    $search
     * @param callable $callback    function($value, $key) { ... }
     *
     * @return array    If match found, returns array($key => $value, ...).
     *                  Otherwise returns empty array.
     */
    public static function findAll(array $search, $callback)
    {
        $matches = array();
        foreach ($search as $key => $value) {
            if (call_user_func($callback, $value, $key)) {
                $matches[$key] = $value;
            }
        }
        return $matches;
    }

    /**
     * Indicates whether array has a value that returns TRUE when passed to $callback.
     *
     * @param array     $search
     * @param callable $callback    function($value, $key) { ... }
     *
     * @return boolean
     */
    public static function exists(array $search, $callback)
    {
        return self::find($search, $callback) ? true : false;
    }

    /**
     * Finds value in associative array that maps to key chain.
     *
     * The key chain is a string of delimited keys use by method to recursively
     * extract value from the passed nested associative array. For example:
     *
     *  $search = array('first' => array('second' => array('third' => 'Hi!')));
     *  $keyChain = 'first.second.third';
     *  $value = findInArrayByKeyChain($search, $keyChain); // returns 'Hi!'
     *
     * @param array $search
     * @param string|array $keyChain    Either '.' deimited string or enumerated
     *                                  array of keys.
     * @param mixed $default
     *
     * @return mixed    Returns found value if valid $keyChain.
     *                  Otherwise, returns $default.
     */
    public static function findByKeyChain(array $search, $keyChain, $default=null)
    {
        if (! is_string($keyChain) && ! is_array($keyChain)) {
            throw new \Exception('$keyChain must be string or array');
        }

        if (is_string($keyChain)) {
            $keyChain = explode('.', $keyChain);
        }

        // Reduce $search by iterating through $keyChain tokens.
        // If token not found, stop iterating and return $default.
        foreach ($keyChain as $keyToken) {
            if (! isset($search[$keyToken])) { return $default; }
            $search = $search[$keyToken];
        }
        return $search;
    }

    /**
     * Indicates whether $a is an associative array.
     *
     * Method checks that (1) $a is an array and (2) it has at least 1 string
     * key.
     *
     * @param mixed $a
     *
     * @return boolean
     */
    public static function isAssociative($a)
    {
        return is_array($a)
            && self::exists(
                array_keys($a),
                function ($key) { return is_string($key); }
            );
    }

    /**
     * Works like implode except delimiter is optional and defaults to '-'.
     *
     * @param array  $a
     * @param string $delim
     *
     * @return string
     */
    public static function concatValues(array $a, $delim='-')
    {
        return implode($delim, (array) $a);
    }

    /**
     * Compares two arrays.
     *
     * Returns TRUE if they contain the exact same set of values
     * (ignores keys).
     *
     * @param array $a1
     * @param array $a2
     *
     * @return boolean
     */
    public static function match(array $a1, array $a2)
    {
        return ! array_diff($a1, $a2) && ! array_diff($a2, $a1);
    }

    /**
     * Determine if all keys exist in given array
     *
     * @param array $arr array to be tested
     * @param mixed $keys could be array or non-fixed number of arguments
     * @return bool if anyone of key does not exist in array, throw exception, otherwise return true
     *
     */
    public static function mustHave(array $arr, $keys)
    {
        $keys = is_array($keys) ? $keys : array_slice(func_get_args(), 1);

        foreach ($keys as $key) {
            if (!isset($arr[$key])) {
                throw new \Exception("{$key} does not exist in given array");
            }
        }
        return true;
    }
}
