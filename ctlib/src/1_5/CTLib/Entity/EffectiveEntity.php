<?php
namespace CTLib\Entity;

abstract class EffectiveEntity extends BaseEntity
{
    
    /**
     * @var boolean
     */
    protected $hasExplicitEffectiveTime;


    /**
     * Constructor.
     *
     * @param array $fieldValues    Associative array of entity field => value.
     */
    public function __construct($fieldValues=array())
    {
        $this->hasExplicitEffectiveTime = false;
        parent::__construct($fieldValues);        
    }

    /**
     * Sets Doctrine-used effectiveTime field as well as custom effectiveTime
     * used to track when user updates value.
     * @param integer $effectiveTime
     * @return void
     */
    public final function setEffectiveTime($effectiveTime)
    {
        $this->effectiveTime            = $effectiveTime;
        $this->hasExplicitEffectiveTime = true;
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
     * Indicates whether entity has had its effectiveTime explicitly set.
     *
     * @return boolean
     */
    public function hasExplicitEffectiveTime()
    {
        return $this->hasExplicitEffectiveTime;
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
