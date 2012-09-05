<?php
namespace CTLib\Entity;

abstract class EffectiveEntity extends BaseEntity
{
    
    
    /**
     * Sets Doctrine-used effectiveTime field as well as custom effectiveTime
     * used to track when user updates value.
     * @param integer $effectiveTime
     * @return void
     */
    public final function setEffectiveTime($effectiveTime)
    {
        $this->effectiveTime	= $effectiveTime;
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
