<?php
namespace CTLib\Listener;

use Symfony\Component\HttpFoundation\Session\Session,
    Symfony\Component\EventDispatcher\EventDispatcher,
    Symfony\Component\Security\Core\Exception\AccessDeniedException,
    Doctrine\ORM\Event;

use CTLib\Entity\EffectiveEntity;

/**
 * Manage all housekeeping fields and convert effective updates to inserts.
 */
class EntityListener
{
    /**
     * @var Session $session
     */
    private $session;

    /**
     * Constructor method.
     *
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Handle prePersist event.
     *
     * This should only be called during an INSERT operation.
     *
     * @param Event\LifecycleEventArgs $eventArgs
     *
     * @todo For the GatewayBundle ??By values should be userId not memberId?
     * @return void
     */
    public function prePersist(Event\LifecycleEventArgs $eventArgs)
    {
        $memberId = $this->session->get('memberId') ?: 0;
        $entity = $eventArgs->getEntity();

        if (method_exists($entity, 'setAddedBy')) {
            $entity->setAddedBy($memberId);
        }

        if (method_exists($entity, 'setAddedOn')) {
            $entity->setAddedOn(time());
        }

        if ($entity instanceof EffectiveEntity) {
            // If EffectiveTime has not been explicitly set,
            // set it to time().
            $entity->setEffectiveTime($entity->getEffectiveTime() ?: time());
        } else {
            if (method_exists($entity, 'setModifiedBy')) {
                $entity->setModifiedBy($memberId);
            }

            if (method_exists($entity, 'setModifiedOn')) {
                $entity->setModifiedOn(time());
            }
        }
    }

    /**
     * Handle onFlush event.
     *
     * @param Event\OnFlushEventArgs $args
     *
     * @return void
     */
    public function onFlush(Event\OnFlushEventArgs $args)
    {
        $entityManager = $args->getEntityManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $this->handleUpdates($entityManager, $unitOfWork);
        $this->handleDeletions($unitOfWork);
    }

    /**
     * Manages entity changes during an UPDATE.
     *
     * This changes the housekeeping fields during an UPDATE.  It also
     * converts effective UPDATEs into INSERTs.
     *
     * @param EntityManager $entityManager
     * @param UnitOfWork    $unitOfWork
     *
     * @todo For the GatewayBundle ??By values should be userId not memberId?
     * @return void
     */
    private function handleUpdates($entityManager, $unitOfWork)
    {
        $memberId = $this->session->get('memberId') ?: 0;

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {

            if ($entity instanceof EffectiveEntity) {

                // Check to see if effectiveTime has been explicitly set.
                $changeSet = $unitOfWork->getEntityChangeSet($entity);

                if (! isset($changeSet['effectiveTime'])) {
                    // effectiveTime was not set by developer. Need to use
                    // either current time or (current effectiveTime + 1) if the
                    // latter happens to be equal to the current time.
                    $effectiveTime = max(
                        time(),
                        $entity->getEffectiveTime() + 1
                    );
                    $entity->setEffectiveTime($effectiveTime);
                }

                // Added__ housekeeping fields need updating
                if (method_exists($entity, 'setAddedOn')) {
                    $entity->setAddedOn(time());
                }

                if (method_exists($entity, 'setAddedBy')) {
                    $entity->setAddedBy($memberId);
                }

                $unitOfWork->detach($entity);
                $entityManager->persist($entity);
                $unitOfWork->computeChangeSet(
                    $entityManager->getClassMetadata(get_class($entity)),
                    $entity
                );

            } else {

                $recompute = false;

                // Update housekeeping fields
                if (method_exists($entity, 'setModifiedOn')) {
                    $entity->setModifiedOn(time());
                    $recompute = true;
                }

                if (method_exists($entity, 'setModifiedBy')) {
                    $entity->setModifiedBy($memberId);
                    $recompute = true;
                }

                if ($recompute) {
                    $unitOfWork->recomputeSingleEntityChangeSet(
                        $entityManager->getClassMetadata(get_class($entity)),
                        $entity
                    );
                }
            }
        }
    }

    /**
     * Deny all effective entity deletions.
     *
     * Currently all effective entities will throw an Exception
     * if a deletion attempt is made.
     *
     * @param UnitOfWork    $unitOfWork
     *
     * @throws \Exception Effective entity instances cannot be deleted.
     * @return void
     */
    private function handleDeletions($unitOfWork)
    {
        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof EffectiveEntity) {
                throw new \Exception(
                    "Entity removal not allowed for EffectiveEntity ("
                    . get_class($entity) . ")"
                );
            }
        }
    }



}
