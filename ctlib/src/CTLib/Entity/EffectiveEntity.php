<?php
namespace CTLib\Entity;

abstract class EffectiveEntity extends BaseEntity
{
    /**
     * @var integer $_effectiveTime
     * Stores user-updated effectiveTime.
     */
    protected $_effectiveTime = null;
    
    /**
     * Sets Doctrine-used effectiveTime field as well as custom effectiveTime
     * used to track when user updates value.
     * @param integer $effectiveTime
     * @return void
     */
    public final function setEffectiveTime($effectiveTime)
    {
        $this->effectiveTime	= $effectiveTime;
        $this->_effectiveTime	= $effectiveTime;
    }

    /**
     * Get standard, Doctrine-used effectiveTime.
     *
     * @return integer $effectiveTime
     */
    public function getEffectiveTime()
    {
        return $this->effectiveTime;
    }
    
    /**
     * Get custom effectiveTime set only when user updates value (not when
     * loaded from database).
     * @return integer $_effectiveTime
     */
    public final function getUpdatedEffectiveTime()
    {
        return $this->_effectiveTime;
    }
    
    /**
     * Throw Exception because effective entities don't have modifiedOn.
     *
     * @param integer $modifiedOn
     * @throws \Exception
     */
    public function setModifiedOn($modifiedOn)
    {
        throw new \Exception("Cannot call on EffectiveEntity");
    }

    /**
     * Throw Exception because effective entities don't have modifiedOn.
     *
     * @return integer $modifiedOn
     * @throws \Exception
     */
    public function getModifiedOn()
    {
        throw new \Exception("Cannot call on EffectiveEntity");
    }

}
