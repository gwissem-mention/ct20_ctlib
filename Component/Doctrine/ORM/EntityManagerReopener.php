<?php
namespace CTLib\Component\Doctrine\ORM;


/**
 * Reopens closed EntityManager and dispatches to listening services.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class EntityManagerReopener
{

    const ON_REPLACE_EVENT = 'entity_manager.replace';


    /**
     * @var Container
     */
    protected $container;


    /**
     * @param Container $container
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * Reopens closed EntityManager.
     *
     * @param EntityManager $entityManager
     * @param string $name
     *
     * @return EntityManager    Returns opened EntityManager.
     */
    public function reopen($entityManager, $name='default')
    {
        if ($entityManager->isOpen()) {
            // Already open.
            return $entityManager;
        }

        $openEntityManager = $entityManager->createPeer();

        $this
            ->container
            ->set("doctrine.orm.{$name}_entity_manager", $openEntityManager);

        $this
            ->container
            ->get('event_dispatcher')        
            ->dispatch(
                self::ON_REPLACE_EVENT,
                new EntityManagerEvent($name, $openEntityManager));

        return $openEntityManager;
    }

    

}