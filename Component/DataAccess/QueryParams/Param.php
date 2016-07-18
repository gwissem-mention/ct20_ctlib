<?php
namespace CTLib\Component\DataAccess\QueryParams;

class Param
{
    /**
     * @var string $value
     */
    public $value;

    /**
     * @var string $field
     */
    public $field;

    /**
     * @var string $name
     */
    public $name;

    /**
     * @var string $op
     */
    public $op = 'eq';

    /**
     * @var string $delimiter
     */
    public $delimiter;

    /**
     * @var boolean $required
     */
    private $required = false;


    public function __construct($param)
    {
        $this->name = $param;
        $this->field = $param;
    }

    /**
     * pull back required param
     *
     * @return bool $required
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * Provides additional logic to the setting of the value variable
     *
     * @param string $value
     * @return QueryBuilderFilterConfig
     */
    public function setValue($value)
    {
        if ($this->delimiter) {
            $value = explode($this->delimiter, $value);
        }

        $this->value = $value;
    }

    /**
     * Provides additional logic for setting the required variable
     *
     * @param boolean $required
     * @return QueryBuilderFilterConfig
     */
    public function setRequired($required)
    {
        $this->required = (bool) $required;
    }

    /**
     * Setter for field variable
     *
     * @param string $field
     * @return QueryBuilderFilterConfig
     */
    public function setField($field)
    {
        $this->field = $field;
    }

    /**
     * Setter for op variables
     *
     * @param string $op
     * @return QueryBuilderFilterConfig
     */
    public function setOp($op)
    {
        $this->op = $op;
    }

    /**
     * setter for delimiter variable
     *
     * @param string $delimiter
     * @return QueryBuilderFilterConfig
     */
    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
    }
}

