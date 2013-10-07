<?php

namespace CTLib\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Unique extends Constraint
{
    public $message = 'This value already exists in database.';
    
    public $entity;
    public $property;

    public function defaultOption()
    {
        return 'property';
    }

    public function requiredOptions()
    {
        return array('entity', 'property');
    }

    public function validatedBy()
    {
        return 'validator.unique';
    }

//    /**
//     * {@inheritDoc}
//     */
//    public function getTargets()
//    {
//        return self::CLASS_CONSTRAINT;
//    }
//    
//    public function targets()
//    {
//        return self::CLASS_CONSTRAINT;
//    }
}
