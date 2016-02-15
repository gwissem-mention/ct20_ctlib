<?php

namespace CTLib\Validator;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\ValidatorException;


/**
 * UniqueValidator
 */
class UniqueValidator extends ConstraintValidator
{
    private $entityManager;
    
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }


    /**
     * Sets the user manager
     *
     * @param UserManagerInterface $userManager
     */
    public function setUserManager(UserManagerInterface $userManager)
    {
        $this->userManager = $userManager;
    }

    
    public function isValid($value, Constraint $constraint) {
        // try to get one entity that matches the constraint
        $user = $this->entityManager->getRepository($constraint->entity)
                ->findUniqueBy(array($constraint->property => $value));
        // if there is already an entity
        if($user != null){
            // the constraint does not pass
            $this->setMessage($constraint->message);
            return false;
        }
        // the constraint passes
        return true;
    }
}
