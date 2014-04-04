<?php
namespace CTLib\Helper;

class JavascriptPrimitive
{
    const TYPE_STRING     = "string";
    const TYPE_NUMBER     = "number";
    const TYPE_BOOL       = "bool";
    const TYPE_NULL       = "null";
    const TYPE_FUNCTION   = "function";
    const TYPE_EXPRESSION = "expression";

    private static $PRIMITIVES = array(
        JavascriptPrimitive::TYPE_STRING     => "/^('([^']*|\\\\')*[^\\\\]'|\"([^\"]*|\\\\\")*[^\\\\]\")\s*[,\]}]/",
        JavascriptPrimitive::TYPE_NUMBER     => "/^([-+]?([0-9]+\.?[0-9]*|\.[0-9]+)([eE][-+]?[0-8]+)?)/",
        JavascriptPrimitive::TYPE_BOOL       => "/^(true|false)/",
        JavascriptPrimitive::TYPE_NULL       => "/^(null|undefined)/",
        JavascriptPrimitive::TYPE_FUNCTION   => "/^(function\s*\([\w\s,$]*\)\s*({([^{}]*|(?2))*}))/",
        JavascriptPrimitive::TYPE_EXPRESSION => "/^(([^,\]}:{\[]*|'([^']*|\\\\')*[^\\\\]'|\"([^\"]*|\\\\\")*[^\\\\]\")*)[,\]}]/"
    );

    private $value = null;
    private $type = null;
    private $matched = null;

    public function __construct($value = null, $type = null, $matched = null)
    {
        if (is_array($value) || is_object($value)) {
            throw new \Exception("Value has to be primitive");
        }

        $this->value = $value;
        $this->type = $type;
        $this->matched = $matched;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getMatched()
    {
        return $this->matched;
    }

    public static function getMatchPattern($type)
    {
        return self::$PRIMITIVES[$type];
    }

    public function __toString()
    {
        if ($this->type == self::TYPE_BOOL) {
            return $this->value ? "true" : "false";
        }
        return (string)$this->value;
    }

    public function toArray()
    {
        return array(
            "value" => $this->value,
            "type"  => $this->type
        );
    }

    public static function match($jsString)
    {
        $result = self::matchPatterns($jsString, JavascriptPrimitive::$PRIMITIVES);
        if ($result === false) return false;

        list($type, $match) = $result;
        $value = $match[1];

        switch ($type) {
            case JavascriptPrimitive::TYPE_STRING:
                return new JavascriptPrimitive(
                    trim($value, " '\""),
                    $type,
                    $match[1]
                );

            case JavascriptPrimitive::TYPE_NUMBER:
                if (ctype_digit(strval($value))) {
                    $value = (int)$value;
                }
                else {
                    $value = (float)$value;
                }
                return new JavascriptPrimitive(
                    $value,
                    $type,
                    $match[0]
                );

            case JavascriptPrimitive::TYPE_BOOL:
                if ($value === "true") {
                    $value = true;
                }
                else {
                    $value = false;
                }
                return new JavascriptPrimitive(
                    $value,
                    $type,
                    $match[0]
                );

            case JavascriptPrimitive::TYPE_NULL:
                return new JavascriptPrimitive(
                    null,
                    $type,
                    $match[0]
                );

            case JavascriptPrimitive::TYPE_FUNCTION:
                return new JavascriptPrimitive(
                    trim($value),
                    $type,
                    $match[0]
                );

            case JavascriptPrimitive::TYPE_EXPRESSION:
                return new JavascriptPrimitive(
                    trim($value),
                    $type,
                    $match[1]
                );

            default:
                throw new \Exception("Syntax Error: Unsupported Type at {$jsString}");
        }
    }

    protected static function matchPatterns($jsString, $patterns)
    {
        if (is_string($patterns)) {
            $patterns = array($patterns);
        }

        foreach ($patterns as $key => $p) {
            $isMatch = preg_match($p, $jsString, $match);
            if ($isMatch) {
                return array($key, $match);
            }
        }

        return false;
    }
}
