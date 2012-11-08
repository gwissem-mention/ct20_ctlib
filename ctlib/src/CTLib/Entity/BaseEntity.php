<?php
/**
 * CellTrak VisitManager 2.x.
 *
 * @package CTLib
 */

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
        foreach ($fieldValues as $field => $value) {
            $setter = 'set' . ucfirst($field);
            $this->$setter($value);
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
     */
    public function update($fieldValues=array())
    {
        foreach ($fieldValues as $field => $value) {
            $setter = 'set' . ucfirst($field);
            $this->$setter($value);
        }
    }


}
