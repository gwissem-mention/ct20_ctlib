<?php

namespace CTLib\Component\Doctrine\ORM;

/**
 * Class to hold delta of changes of an entity.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class EntityDelta implements \JsonSerializable
{
    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var string $fieldName
     * @var mixed  $oldValue
     * @var mixed  $newValue
     *
     * @return void
     */
    public function add($fieldName, $oldValue, $newValue)
    {
        $this->fields[$fieldName]['oldValue'] = $oldValue;
        $this->fields[$fieldName]['newValue'] = $newValue;
    }

    /**
     * @param string $fieldName
     *
     * @return bool
     */
    public function hasDiff($fieldName)
    {
        return isset($this->fields[$fieldName]);
    }

    /**
     * @param string $fieldName
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function getDiff($fieldName)
    {
        if (!$this->hasDiff($fieldName)) {
            throw new \InvalidArgumentException("EntityDelta: $fieldName does not exist in delta");
        }
        return $this->fields[$fieldName];
    }

    /**
     *
     */
    public function jsonSerialize()
    {
        return $this->fields;
    }
}
