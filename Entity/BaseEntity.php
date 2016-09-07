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
abstract class BaseEntity implements TrackableEntity
{
    /**
     * Constants for tracking state
     */
    const UNCHANGED = 0;
    const CREATE    = 1;
    const MODIFY    = 2;

    /**
     * @var int
     */
    protected $trackingState = 0;

    /**
     * @var array
     */
    protected $modifiedProperties = [];


    /**
     * Constructor.
     *
     * @param array $fieldValues    Associative array of entity field => value.
     * @param bool  $track          Determines whether or not to start change tracking
     */
    public function __construct($fieldValues=array(), $track=false)
    {
        if ($track) {
            $this->beginNew();
        }

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

    /**
     * @param $fieldName
     * @param $oldValue
     */
    protected function setModifiedProperty($fieldName, $oldValue)
    {
        $this->modifiedProperties[$fieldName] = $oldValue;
    }

    /**
     * @return bool
     */
    public function getIsTracking()
    {
        return $this->getTrackingState() != self::UNCHANGED;
    }


    /**
     * @inheritdoc
     */
    public function getTrackingState()
    {
        return $this->trackingState;
    }

    /**
     * @inheritdoc
     */
    public function getModifiedProperties()
    {
        return $this->modifiedProperties;
    }

    /**
     * @inheritdoc
     */
    public function beginNew()
    {
        if ($this->trackingState != self::UNCHANGED) {
            return;
        }
        $this->trackingState = self::CREATE;
        $this->modifiedProperties = [];
    }

    /**
     * @inheritdoc
     */
    public function endNew()
    {
        $this->trackingState = self::UNCHANGED;
        $this->modifiedProperties = [];
    }

    /**
     * @inheritdoc
     */
    public function beginEdit()
    {
        if ($this->trackingState != self::UNCHANGED) {
            return;
        }
        $this->trackingState = self::MODIFY;
        $this->modifiedProperties = [];
    }

    /**
     * @inheritdoc
     */
    public function endEdit()
    {
        $this->trackingState = self::UNCHANGED;
        $this->modifiedProperties = [];
    }
}
