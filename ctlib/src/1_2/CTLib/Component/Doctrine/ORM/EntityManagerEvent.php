<?php
namespace CTLib\Component\Doctrine\ORM;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event dispatched when a change is a made to an EntityManager (i.e., when
 * it's been replaced by a new instance).
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class EntityManagerEvent extends Event
{

    /**
     * @var string $entityManagerName
     */
    protected $entityManagerName;

    /**
     * @var EntityManager $entityManager
     */
    protected $entityManager;

    /**
     * @param string $entityManagerName
     * @param EntityManager $entityManager  Current EntityManager instance.
     */
    public function __construct($entityManagerName, $entityManager)
    {
        $this->entityManagerName    = $entityManagerName;
        $this->entityManager        = $entityManager;
    }

    /**
     * Returns $entityManagerName.
     *
     * @return string
     */
    public function getEntityManagerName()
    {
        return $this->entityManagerName;
    }

    /**
     * Returns current EntityManager instance.
     *
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * Indicates whether this event is for EntityManager referenced by $name.
     *
     * @param string $name
     * @return boolean
     */
    public function isEntityManager($name)
    {
        return $this->entityManagerName == $name;
    }

}