<?php
namespace CTLib\Component\ObjectWalker;

use CTLib\Util\Util;

/**
 * Processes and validates public properties in an object.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class ObjectWalker implements \JsonSerializable
{
    /**
     * @var \stdClass
     */
    protected $object;

    /**
     * @var string
     */
    protected $myProperty;

    /**
     * @var array
     */
    protected $touchedProperties;


    /**
     * @param object $object        Source object to process.
     * @param string $myProperty    Name of source object if it's part of a
     *                              parent object.
     */
    public function __construct($object, $myProperty=null)
    {
        if (! is_object($object)) {
            throw new \Exception('$object must be an object');
        }
        $this->object = $object;
        $this->myProperty = $myProperty;
        $this->touchedProperties = array();
    }

    /**
     * Indicates whether object contains $property.
     *
     * @param string $property
     * @param callable $isValidCallback     function($value) { return boolean }
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value doesn't pass $isValidCallback.
     */
    public function has($property, $isValidCallback=null)
    {
        if (! isset($this->object->{$property})) { return false; }

        $this->touchedProperties[] = $property;

        if ($isValidCallback) {
            $value = $this->object->{$property};
            if (! call_user_func($isValidCallback, $value)) {
                $property = $this->qualifyProperty($property);
                throw new MalformedObjectException(
                    "Invalid value for property: {$property}",
                    $this->object
                );    
            }
        }
        return true;
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be an integer.
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not an integer.
     */
    public function hasInteger($property)
    {
        return $this->has($property, 'is_int');
    }

    /**
     * Alias of ObjectWalker::hasInteger.
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not an integer.
     */
    public function hasInt($property)
    {
        return $this->hasInteger($property);
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be a float or integer.
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not a float.
     */
    public function hasFloat($property)
    {
        return $this->has($property, function($v) {
            return is_float($v) || is_int($v);
        });
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be a string.
     *
     * @param string $property
     * @param array $validSet   Enumerated array of allowed values.
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not a string or is not
     *                                      in $validSet.
     */
    public function hasString($property, array $validSet=null)
    {
        return $this->has($property, function($v) use ($validSet) {
            return is_string($v) && (! $validSet || in_array($v, $validSet));
        });
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be a string no longer than $maxLength.
     *
     * @param string $property
     * @param int $maxLength
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not a string or is longer
     *                                      than $maxLength.
     */
    public function hasMaxString($property, $maxLength)
    {
        if (! is_int($maxLength) || $maxLength <= 0) {
            throw new \Exception('$maxLength must be int greater than 0');
        }
        return $this->has($property, function($v) use ($maxLength) {
            return is_string($v) && strlen($v) <= $maxLength;
        });
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be a boolean.
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not a boolean.
     */
    public function hasBoolean($property)
    {
        return $this->has($property, 'is_bool');
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be an array.
     *
     * @param string $property
     * @param callable $validItemCallback   function($value) { return boolean }
     *                                      All items in array must pass this
     *                                      callback.
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not an array or all of the
     *                                      array's items don't pass
     *                                      $validItemCallback.
     */
    public function hasArray($property, $validItemCallback=null)
    {
        return $this->has($property, function($v) use ($validItemCallback) {
            if (! is_array($v)) { return false; }
            if (! $validItemCallback) { return true; }
            foreach ($v as $item) {
                if (! call_user_func($validItemCallback, $item)) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Simply calls hasArray to support getArrayRaw.
     */
    public function hasArrayRaw($property, $validItemCallback=null)
    {
        return $this->hasArray($property, $validItemCallback);
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be a non-empty array.
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not an array or array is
     *                                      empty.
     */
    public function hasNonEmptyArray($property)
    {
        return $this->has($property, function($v) {
            return is_array($v) && count($v);
        });
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be an object.
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not an object.
     */
    public function hasObject($property)
    {
        return $this->has($property, function($v) {
            return is_object($v);
        });
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be an integer greater than 0.
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not an integer greater than
     *                                      0.
     */
    public function hasIntGTZero($property)
    {
        return $this->has($property, function($v) {
            return is_int($v) && $v > 0;
        });
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be an unsigned integer (>= 0).
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not an unsigned int.
     */
    public function hasUnsignedInt($property)
    {
        return $this->has($property, function($v) {
            return is_int($v) && $v >= 0;
        });
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be an 'id' (integer greater than 0).
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not an 'id'.
     */
    public function hasId($property)
    {
        return $this->hasIntGTZero($property);
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be an integer.
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not an integer.
     */
    public function hasTimestamp($property)
    {
        return $this->hasInteger($property);
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be a valid regular expression.
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not a valid regular exp.
     */
    public function hasRegExp($property)
    {
        return $this->has($property, function($v) {
            return is_string($v) && preg_match($v, '') !== false;
        });
    }

    /**
     * Indicates whether object contains $property. If it does, its value
     * must be a valid ISO 8601 date time string.
     *
     * @param string $property
     *
     * @return boolean
     * @throws MalformedObjectException     If object has $property but its
     *                                      value is not a valid ISO date time.
     */
    public function hasISODateTime($property)
    {
        return $this->has($property, function($v) {
            $pattern =  '/^' .
                        '\d{4}-\d{2}-\d{2}' .           // date
                        'T\d{2}:\d{2}:\d{2}(\.\d+)?' .  // time
                        'Z|((\+|-)\d{2}:\d{2})' .       // timezone
                        '$/';
            if (preg_match($pattern, $v) !== 1) { return false; }
            try {
                new \DateTime($v); return true;
            } catch (\Exception $e) { return false; }
        });
    }

    /**
     * Throws MalformedObjectException if object contains $property.
     *
     * @param string $property,...
     *
     * @throws MalformedObjectException     If object has $property.
     */
    public function mustNotHave($property)
    {
        foreach (func_get_args() as $property) {
            if ($this->has($property)) {
                $property = $this->qualifyProperty($property);
                throw new MalformedObjectException(
                    "Unsupported property: {$property}",
                    $this->object
                );
            }    
        }
        return;
    }

    /**
     * Returns timestamp value for $property.
     *
     * NOTE: Converts 13 digit millisecond timestamp to 10 digit second timestamp.
     *
     * @param string $property
     *
     * @return integer
     * @throws MalformedObjectException     If object has $property but it's
     *                                      not an integer.
     */
    public function getTimestamp($property)
    {
        if (! $this->hasTimestamp($property)) { return null; }

        $ts = $this->getValue($property);
        if (strlen($ts) == 13) {
            return Util::millisecsToSecs($ts);
        } else {
            return $ts;
        }
    }

    /**
     * Returns DateTime value for $property.
     *
     * @param string $property
     *
     * @return DateTime
     * @throws MalformedObjectException     If object has $property but it's
     *                                      not an ISO date time.
     */
    public function getISODateTime($property)
    {
        if (! $this->hasISODateTime($property)) { return null; }

        try {
            return new \DateTime($this->getValue($property));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Supports:
     *
     *  - mustHave($property, $isValidCallback=null)
     *  - mustHave{Type}($property)
     *
     *      Work like ObjectWalker::has* but throw MalformedObjectException if
     *      object doesn't have $property.
     *
     *  - get($property, $isValidCallback=null)
     *  - get{Type}($property)
     *
     *      If corresponding ObjectWalker::has* returns true, get* returns value
     *      for $property. Otherwise returns null.
     *
     *  - mustGet($property, $isValidCallback=null)
     *  - mustGet{Type}($property)
     *
     *      Work like ObjectWalker::get* but throw MalformedObjectException if
     *      object doesn't have $property.
     */
    public function __call($methodName, $args)
    {
        if (stripos($methodName, 'mustHave') === 0) {
            $hasMethodName = 'has' . substr($methodName, 8);
            if (! call_user_func_array(array($this, $hasMethodName), $args)) {
                $property = $this->qualifyProperty($args[0]);
                throw new MalformedObjectException(
                    "Missing property: {$property}",
                    $this->object
                );
            }
            return;
        }

        if (stripos($methodName, 'get') === 0) {
            $hasMethodName = 'has' . substr($methodName, 3);
            if (! call_user_func_array(array($this, $hasMethodName), $args)) {
                return null;
            }
            if(stripos($methodName, 'Raw') > 0) {
                return $this->getRawValue($args[0]);
            }
            return $this->getValue($args[0]);
        }

        if (stripos($methodName, 'mustGet') === 0) {
            $getMethodName = substr($methodName, 4);
            $value = call_user_func_array(array($this, $getMethodName), $args);
            if (is_null($value)) {
                $property = $this->qualifyProperty($args[0]);
                throw new MalformedObjectException(
                    "Missing property: {$property}",
                    $this->object
                );
            }
            return $value;
        }

        throw new \Exception("Invalid method: {$methodName}");
    }

    /**
     * Returns property assigned to source object.
     *
     * @return string|null
     */
    public function getMyProperty()
    {
        return $this->myProperty;
    }

    /**
     * Returns all public properties in source object.
     *
     * @return array
     */
    public function getProperties()
    {
        return array_keys((array) $this->object);
    }

    /**
     * Returns number of all public properties in source object.
     *
     * @return int
     */
    public function getPropertyCount()
    {
        return count($this->getProperties());
    }

    /**
     * Returns public properties in source object that have not been
     * touched (accessed) via a has, mustHave, get or mustGet methods.
     *
     * @return array
     */
    public function getUntouchedProperties()
    {
        return array_diff($this->getProperties(), $this->touchedProperties);
    }

    /**
     * Returns object source for this walker.
     *
     * @return stdObject
     */
    public function getSourceObject()
    {
        return $this->object;
    }

    /**
     * Returns value for $property.
     *
     * @param string $property
     * @return mixed            Returns objects (including those in array) as
     *                          ObjectWalker instances.
     */
    protected function getValue($property)
    {
        if (! isset($this->object->{$property})) {
            $property = $this->qualifyProperty($property);
            throw new MalformedObjectException(
                "Missing property: {$property}",
                $this->object
            );
        }
        $value = $this->object->{$property};

        if (is_object($value)) {
            return new self($value, $this->qualifyProperty($property));
        } elseif (is_array($value)) {
            $values = [];
            $property = $this->qualifyProperty($property);
            foreach ($value as $i => $item) {
                if (is_object($item)) {
                    $item = new self($item, "{$property}[{$i}]");
                }
                $values[] = $item;
            }
            return $values;
        } else {
            return $value;
        }
    }

    /**
     * Returns raw json value for $property.
     *
     * @param string $property
     * @return mixed
     */
    protected function getRawValue($property)
    {
        if (!isset($this->object->{$property})) {
            $property = $this->qualifyProperty($property);
            throw new MalformedObjectException(
                "Missing property: {$property}",
                $this->object
            );
        }
        return $this->object->{$property};
    }

    /**
     * Qualifies $property with property assigned to source object.
     *
     * @param string $property
     * @return string
     */
    protected function qualifyProperty($property)
    {
        return $this->myProperty ? "{$this->myProperty}.{$property}" : $property;
    }

    /**
     * Returns JSON-encoded source object.
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->object);
    }

    /**
     * Returns JSON-encoded source object.
     *
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->object;
    }

    /**
     * Creates ObjectWalker from associative array.
     *
     * @param array $source     array($property => $value, ...)
     * @param array $castings   array('int', 'str', 'bool', 'float')
     *                          Used to cast non-null string values to their
     *                          correct type.
     * @param string $myProperty    Name of source object if it's part of a
     *                              parent object.
     *
     * @return ObjectWalker
     */
    public static function createFromArray(array $source, array $castings=null, $myProperty=null)
    {
        if (! $castings) {
            $object = (object) $source;
        } else {
            if (count($source) != count($castings)) {
                throw new \Exception('Count mismatch with $source and $castings');
            }
            $i = 0;
            $object = new \stdClass;
            foreach ($source as $property => $value) {
                if (! is_null($value)) {
                    switch ($castings[$i]) {
                        case 'str':
                        case 's':
                            $value = (string) $value;
                            break;
                        case 'int':
                        case 'i':
                            $value = (int) $value;
                            break;
                        case 'bool':
                        case 'b':
                            $value = (bool) $value;
                            break;
                        case 'float':
                        case 'f':
                            $value = (float) $value;
                            break;
                        case 'array':
                        case 'a':
                            if (! is_array($value)) {
                                $value = @json_decode($value);
                                if (is_null($value)) {
                                    throw new \Exception();
                                }
                            }
                            break;
                        case 'object':
                        case 'o':
                            if (! is_object($value)) {
                                $value = @json_decode($value);
                                if (is_null($value)) {
                                    throw new \Exception();
                                }
                            }
                            break;

                        default:
                            throw new \Exception("Invalid casting ({$i}): {$castings[$i]}");
                    }
                }
                $object->{$property} = $value;
                $i += 1;
            }
        }
        return new self($object, $myProperty);
    }

}