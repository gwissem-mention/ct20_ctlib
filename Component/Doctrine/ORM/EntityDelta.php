<?php

namespace CTLib\Component\Doctrine\ORM;

/**
 * Class to hold delta of changes of an entity.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class EntityDelta
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
        $fields[$fieldName]['oldValue'] = $oldValue;
        $fields[$fieldName]['newValue'] = $newValue;
    }

    /**
     * @param string $fieldName
     *
     * @return bool
     */
    public function hasDiff($fieldName)
    {
        return isset($fields[$fieldName]);
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
        return $fields[$fieldName];
    }
}
