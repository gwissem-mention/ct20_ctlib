<?php
namespace CTLib\Util;


/**
 * Logical condition built using simple configuration constructs.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class Condition
{
    
    /**
     * @var string
     */
    protected $property;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @var mixed
     */
    protected $test;

    
    /**
     * @param string $property  Property of passed values to test.
     * @param string $operator  Operator used in test.
     * @param mixed $test       Value used to test against.
     */
    public function __construct($property, $operator, $test=null)
    {
        $this->property     = $property;
        $this->operator     = $operator;
        $this->test         = $test;
        $this->applyIfSet   = new ConditionSet;
    }

    /**
     * Indicates whether this condition applies to the passed $values.
     *
     * @param array $values
     * @return boolean
     */
    public function apply(array $values)
    {
        return $this->applyIfSet->isEmpty() || $this->applyIfSet->test($values);
    }

    /**
     * Indicates whether $values pass/fail test condition.
     *
     * @param array $values
     * @return boolean
     */
    public function test(array $values)
    {
        $sourceValue = Arr::get($this->property, $values);

        if (is_null($sourceValue)) {
            // Can't pass test if source value not found.
            return false;
        }

        if (in_array($this->operator, array('*', '!*', '~', '!~'))) {
            // These operators don't require additional handling of test value.
            switch ($this->operator) {
                case '*':
                    // Return true if source value is not empty.
                    return $sourceValue != '';
                case '!*':
                    // Return true if source value is empty.
                    return $sourceValue == '';
                case '~':
                    // Return true if source value matches regular expression.
                    $result = preg_match($this->test, $sourceValue);
                    if ($result === false) {
                        throw new \Exception("Invalid test pattern: {$this->test}");
                    }
                    return $result === 1;
                case '!~':
                    // Return true if source does not match regular expression.
                    $result = preg_match($this->test, $sourceValue);
                    if ($result === false) {
                        throw new \Exception("Invalid test pattern: {$this->test}");
                    }
                    return $result === 0;
                default:
                    throw new \Exception("Invalid operator: {$this->operator}");
            }
        }

        // Check if test is in the form of "{property}". If so, need to find
        // test value from passed $values.
        if (is_string($this->test)
            && preg_match('/^\{([a-z0-9_]+)\}$/i', $this->test, $matches) === 1) {
            $testProperty = $matches[1];
            $testValue = Arr::get($testProperty, $values);

            if (is_null($testValue)) {
                // Can't pass test if test value not found.
                return false;
            }
        } else {
            $testValue = $this->test;
        }

        // May need to coerce test value into different type in order to compare
        // with source value.
        $testValue = $this->coerceTestValue($testValue, $sourceValue);

        switch ($this->operator) {
            case '=':
                // Return true if source value equals test value.
                return $sourceValue == $testValue;
            case '!=':
                // Return true if source value does not equal test value.
                return $sourceValue != $testValue;
            case '>':
                // Return true if source value is greater than test value.
                return $sourceValue > $testValue;
            case '>=':
                // Return true if source value is greater than or equal to test
                // value.
                return $sourceValue >= $testValue;
            case '<':
                // Return true if source value is less than test value.
                return $sourceValue < $testValue;
            case '<=':
                // Return true if source value is less than or equal to test
                // value.
                return $sourceValue <= $testValue;
            case 'in':
                // Return true if source value exists in test value.
                return in_array($sourceValue, $testValue);
            case '!in':
                // Return true if source value does not exist in test value.
                return ! in_array($sourceValue, $testValue);
            case '&':
                // Return true if source value intersects with test value.
                return count(array_intersect($sourceValue, (array)$testValue)) != 0;
            case '!&':
                // Return true if source value does not intersect with test value.
                return count(array_intersect($sourceValue, (array)$testValue)) == 0;
            default:
                throw new \Exception("Invalid operator: {$this->operator}");
        }
    }

    /**
     * Adds apply-if condition.
     *
     * @param Condition $condition
     * @return void
     */
    public function addApplyIfCondition($condition)
    {
        $this->applyIfSet->add($condition);
    }

    /**
     * Returns $property.
     *
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * Returns $operator.
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Returns $test.
     *
     * @return mixed
     */
    public function getTest()
    {
        return $this->test;
    }

    /**
     * Coerces test value into different type in order to compare with source
     * value.
     *
     * @param mixed $testValue
     * @param mixed $sourceValue
     *
     * @return mixed
     */
    protected function coerceTestValue($testValue, $sourceValue)
    {
        if ($sourceValue instanceof \DateTime
            && ! ($testValue instanceof \DateTime)) {
            $testValue = new \DateTime($testValue);
        }

        return $testValue;
    }

}