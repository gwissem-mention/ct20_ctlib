<?php

namespace CTLib\Entity;

/**
 * Base entity extended by all CellTrak entities.
 */
abstract class BaseEntity
{
    /**
     * Constructor.
     *
     * @param array $fieldValues    Associative array of entity field => value.
     */
    public function __construct($fieldValues=array())
    {
        if ($fieldValues) {
            $this->update($fieldValues);
        }
    }

    /**
     * Set addedOn.
     *
     * @param integer $addedOn
     */
    public function setAddedOn($addedOn)
    {
        $this->addedOn = $addedOn;
    }

    /**
     * Get addedOn.
     *
     * @return integer $addedOn
     */
    public function getAddedOn()
    {
        return $this->addedOn;
    }

    /**
     * Set modifiedOn.
     *
     * @param integer $modifiedOn
     */
    public function setModifiedOn($modifiedOn)
    {
        $this->modifiedOn = $modifiedOn;
    }

    /**
     * Get modifiedOn.
     *
     * @return integer $modifiedOn
     */
    public function getModifiedOn()
    {
        return $this->modifiedOn;
    }

    /**
     * Update the fieldValues from array.
     *
     * @param array $fieldValues    Associative array of entity field => value.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function update($fieldValues=array())
    {
        foreach ($fieldValues as $field => $value) {
            $setter = 'set' . ucfirst($field);
            if (! method_exists($this, $setter)) {
                throw new \Exception("Invalid field '{$field}' for " . get_class($this));
            }
            $this->$setter($value);
        }
    }

    /**
     * Returns values for multiple fields.
     *
     * @param array $fields     array($fieldName1, $fieldName2, ...)
     * @return array            array($fieldName1 => $fieldValue1, ...)
     *
     * @throws \Exception
     */
    public function multiGet($fields)
    {
        $values = array();
        foreach ($fields as $field) {
            $getter = 'get' . ucfirst($field);
            if (! method_exists($this, $getter)) {
                throw new \Exception("Invalid field '{$field}' for " . get_class($this));
            }
            $values[$field] = $this->$getter();
        }
        return $values;
    }
}
