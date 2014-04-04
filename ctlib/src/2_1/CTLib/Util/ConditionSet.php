<?php
namespace CTLib\Util;


/**
 * Set of Condition objects joined with 'AND' operator.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class ConditionSet
{
    /**
     * @var array
     */
    protected $conditions;


    public function __construct()
    {
        $this->conditions = array();
    }

    /**
     * Adds condition into set.
     *
     * @param Condition $condition
     * @return void
     */
    public function add($condition)
    {
        $this->conditions[] = $condition;
    }

    /**
     * Tests values against conditions.
     *
     * All conditions must evalulate to true for ConditionSet::test to
     * return true.
     *
     * @param array $values
     * @return boolean
     */
    public function test(array $values)
    {
        if (! $this->conditions) {
            throw new \Exception("No conditions added");    
        }

        foreach ($this->conditions as $condition) {
            if (! $condition->apply($values)) { continue; }
            if (! $condition->test($values)) { return false; }
        }
        return true;
    }

    /**
     * Returns $conditions.
     *
     * @return array
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Indicates whether ConditionSet has no conditions added.
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return empty($this->conditions);
    }

}