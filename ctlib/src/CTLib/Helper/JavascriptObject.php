<?php
namespace CTLib\Helper;

use CTLib\Util\Util;

/**
 * This class can parse javascript json object.
 *
 */

class JavascriptObject
{
    private static $CLOSINGS = array(",", "}", "]");

    private $jsString = null;
    private $origJsString = null;
    private $jsObject = null;
    private $isDirty = false;

    function __construct($js = "")
    {
        if (is_string($js)) {
            $this->jsString = $this->origJsString = $js;
        }
        elseif(is_object($js)) {
            $this->jsObject = $js;
        }
    }

    public function __toString()
    {
        if (!$this->isDirty) {
            return $this->cleanJson($this->origJsString);
        }
        return $this->toJson();
    }

    public function __get($name)
    {
        $object = $this->toObject();
        return isset($object->{$name}) ? $object->{$name}->getValue() : null;
    }

    public function cleanJson($json)
    {
        // Disable cleaning operation for now.
        return $json;

        $json = preg_replace("/=\s*{([^{}]*)}[ \t\v\f]*[\r\n]*[ \t\v\f]*/", '={$1};', $json);
        return preg_replace("/(\/\*.*\*\/|\/\/.*[\r\n]|[\t\r\n\v\f]| {2,})/", "", $json);
    }

    public function toJson()
    {
        if (!isset($this->jsObject)) $this->jsObject = $this->parse();
        $json = $this->getJson($this->jsObject);
        return $this->cleanJson($json);
    }

    protected function getJson($jsObject)
    {
        if (is_array($jsObject)) {
            $jsons = array();
            foreach ($jsObject as $value) {
                $jsons[] = $this->getJson($value);
            }
            return "[" . implode(",", $jsons) . "]";
        }

        if ($jsObject instanceof \stdClass) {
            $jsons = "";
            $iterator = get_object_vars($jsObject);
            $c = count($iterator);
            foreach ($iterator as $key => $value) {
                if (!preg_match("/^\w*$/", $key)) {
                    $key = '"'.addslashes($key).'"';
                }
                $jsons .= $key . ":" . $this->getJson($value);
                if (--$c) $jsons .= ",";
            }
            return "{" . $jsons . "}";
        }

        if (!isset($jsObject)
            || $jsObject->getType() == JavascriptPrimitive::TYPE_NULL
        ) {
            return "null";
        }

        if ($jsObject instanceof JavascriptPrimitive
            && $jsObject->getType() == JavascriptPrimitive::TYPE_STRING
            || is_string($jsObject)
        ) {
            return '"' . (string)$jsObject . '"';
        }

        if (is_bool($jsObject)) {
            return $jsObject ? "true" : "false";
        }

        return (string)$jsObject;
    }

    public function toObject()
    {
        return $this->getObject();
    }

    /**
     * This is method setObject
     *
     * @param mixed $obj This is a description
     * @return mixed This is the return value description
     *
     */
    public function setObject($obj)
    {
        $this->jsObject = $obj;
        $this->isDirty = true;
        return $this;
    }

    public function getObject()
    {
        if (!isset($this->jsObject)) $this->jsObject = $this->parse();
        return $this->jsObject;
    }

    /**
     * Merge an object or array or JavascriptObject with this JavascriptObject
     *
     * @param mixed $object The object need to be merged in
     * @return JavascriptObject return JavascriptObject itself
     *
     */
    public function merge($object)
    {
        if (empty($object)) { return $this; }

        if ($object instanceof JavascriptObject) {
            $object = $object->getObject();
        }
        else {
            $object = $this->convertToJavascriptObject($object);
        }
        
        $thisJsObject = $this->getObject();
        if (!$thisJsObject) {
            $this->jsObject = (object)$object;
        }
        else {
            $this->jsObject = (object)array_merge(
                get_object_vars($thisJsObject),
                get_object_vars($object)
            );
        }
        
        $this->isDirty = true;
        return $this;
    }

    /**
     * Convert normal array or stdClass into internal object
     *
     * @param mixed $object object to be converted, can be an array, stdClass or JavascriptPrimitive
     * @return mixed Javascript object that can be accepted by JavascriptObject
     *
     */
    public function convertToJavascriptObject($object)
    {
        if ($object instanceof JavascriptPrimitive) { return $object; }

        if (is_array($object) && $this->isObject($object)) {
            $object = (object)$object;
        }

        if (is_object($object)) {
            $iterator = get_object_vars($object);
            foreach ($iterator as $key => $value) {
                $object->{$key} = $this->convertToJavascriptObject($value);
            }
        }
        else if (is_array($object)) {
            foreach ($object as &$value) {
                $value = $this->convertToJavascriptObject($value);
            }
        }
        else if (is_int($object) || is_float($object)) {
            $object = new JavascriptPrimitive($object, JavascriptPrimitive::TYPE_NUMBER);
        }
        else if (is_bool($object)) {
            $object = new JavascriptPrimitive(
                $object ? "true" : "false",
                JavascriptPrimitive::TYPE_BOOL
            );
        }
        else if (is_string($object)) {
            $isFunction = preg_match(
                JavascriptPrimitive::getMatchPattern(JavascriptPrimitive::TYPE_FUNCTION),
                trim($object),
                $match
            );
            if ($isFunction) {
                $object = new JavascriptPrimitive($object, JavascriptPrimitive::TYPE_FUNCTION);
            }
            else {
                $object = new JavascriptPrimitive($object, JavascriptPrimitive::TYPE_STRING);
            }
        }
        else {
            throw new \Exception("type can not be supported");
        }
        return $object;
    }

    /**
     * Determine if the given array is a javascript object
     *
     * @param array $array javascript array or object
     * @return boolean if array is a javascript array or object
     *
     */
    protected function isArray($array)
    {
        if (!is_array($array) || empty($array)) return false;
        return count(array_filter(array_keys($array), 'is_string')) == 0;
    }

    /**
     * Determine if the given array is a javascript object
     *
     * @param array $array javascript array or object
     * @return boolean if array is a javascript array or object
     *
     */
    protected function isObject($array)
    {
        if (!is_array($array) || empty($array)) return false;
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }

    /**
     * Parse the javascript object string
     *
     * @return mixed return javascript object or Primitives
     *
     */
    protected function parse()
    {
        $this->jsString = trim($this->jsString);
        if (empty($this->jsString)) return null;

        if ($this->testPrimitive()) {
            $primitive = $this->expectPrimitive();
            if (!$this->test(JavascriptObject::$CLOSINGS) && strlen($this->jsString) != 0) {
                throw new \Exception("Syntax invalid, expect closing at {$this->jsString}");
            };
            return $primitive;
        }

        if ($this->test("[")) {
            return $this->parseArray();
        }

        if ($this->test("{")) {
            return $this->parseObject();
        }

        if (strlen($this->jsString) != 0) {
            throw new \Exception("Syntax invalid, unexpected character at {$this->jsString}");
        }
    }

    /**
     * Parse object
     *
     * @return array parsed associate array
     *
     */
    protected function parseObject()
    {
        $object = new \stdClass();
        $this->expect("{");
        do {
            $key = $this->expectKey();
            $val = $this->parse();
            $object->{$key} = $val;
        }
        while ($this->testAndExpect(","));
        $this->expect("}");
        return $object;
    }

    /**
     * Parse array
     *
     * @return array parsed array
     *
     */
    protected function parseArray()
    {
        $array = array();
        $this->expect("[");
        do {
            $val = $this->parse();
            $array[] = $val;
        }
        while ($this->testAndExpect(","));
        $this->expect("]");
        return $array;
    }

    /**
     * Test and Expect the key word. If the beginning of the string
     * matches the keyword, do an additional expect
     *
     * @param string $key Key word to be matched
     * @return string If the key match beginning of the string
     *
     */
    protected function testAndExpect($key)
    {
        if (!$this->test($key)) return false;
        $this->expect($key);
        return true;
    }

    /**
     * Expect the string start with given key word
     *
     * @param string $key Key word to be matched
     * @return string The string left after matching beginning of the key word
     *
     */
    protected function expect($key)
    {
        $match = $this->matchBeginning($key);
        if ($match === false) {
            throw new \Exception("Syntax is invalid, expect {$key} at {$this->jsString}");
        }
        $this->pass($match);
        return $key;
    }

    /**
     * Determine if string start with given key word
     *
     * @param string $key Key word to be matched
     * @return boolean If the key match beginning of the string
     *
     */
    protected function test($key)
    {
        return $this->matchBeginning($key) !== false;
    }

    /**
     * Jump over the given string
     *
     * @param string $str string to be skipped over
     * @return string string left after skipped over
     *
     */
    protected function pass($str)
    {
        $sub = substr($this->jsString, strlen($str));
        if (empty($sub) && strlen($str) != strlen($this->jsString)) {
            throw new \Exception("Syntax Error: at {$this->jsString}");
        }
        return $this->jsString = trim($sub);
    }

    /**
     * Expect the next word to be the key of javascript object
     *
     * @return string key
     *
     */
    protected function expectKey()
    {
        preg_match("/^([\w$]+|'[^']*'|\"[^\"]*\"):/", $this->jsString, $match);
        if (empty($match)) {
            throw new \Exception("Syntax Error: Expect Key at {$this->jsString}");
        }
        $this->pass($match[0]);
        return trim($match[1], " '\"");
    }

    /**
     * Expect the next word to be javascript primitive
     *
     * @return JavascriptPrimitive Primitive of javascript object
     *
     */
    protected function expectPrimitive()
    {
        $primitive = JavascriptPrimitive::match($this->jsString);
        if ($primitive === false) {
            throw new \Exception("Syntax is invalid, expect primitives at {$this->jsString}");
        }
        $this->pass($primitive->getMatched());
        return $primitive;
    }

    /**
     * Determine if the js string start with javascript primitive
     *
     * @return string true if primitive found. false if not found
     *
     */
    protected function testPrimitive()
    {
        return JavascriptPrimitive::match($this->jsString) !== false;
    }

    /**
     * Match given string with beginning of the js string
     *
     * @param string $strings String that wants to be matched
     * @return mixed Matched string in js string. Return false if match can not be found
     *
     */
    protected function matchBeginning($strings)
    {
        if (is_string($strings)) $strings = array($strings);

        $strings = preg_replace("/[{}.\[\]^$*,|]/", "\\\\$0", $strings);
        $pattern = "/^(" . implode("|", $strings) . ")/";

        preg_match($pattern, $this->jsString, $match);
        if (empty($match)) return false;

        return $match[0];
    }
}
