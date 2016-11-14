<?php
namespace CTLib\Component\ActionLog;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Yaml\Yaml;
use CTLib\Util\Util;
use CTLib\Component\Doctrine\ORM\EntityDelta;
use CTLib\Component\EntityFilterCompiler\EntityFilterCompiler;
use CTLib\Component\Doctrine\ORM\EntityManager;
use CTLib\Component\CtApi\CtApiCaller;
use CTLib\Component\Monolog\Logger;


/**
 * Class ActionLogger
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class ActionLogger
{

    /**
     * API endpoint for posting action logs.
     */
    const ACTION_LOG_API_PATH = '/actionLogs';


    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var CtApiCaller
     */
    protected $ctApiCaller;

    /**
     * @var Kernel
     */
    protected $kernel;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Tags log entries with this source.
     * @var string
     */
    protected $source;

    /**
     * Set of registered EntityFilterCompilers used to apply entity's filters
     * to log entry.
     * @var array
     */
    protected $filterCompilers = [];


    /**
     * @param EntityManager $entityManager
     * @param CtApiCaller $ctApiCaller
     * @param Kernel $kernel
     * @param Logger $logger
     * @param string $source
     * @param array $actionFiles
     */
    public function __construct(
        EntityManager $entityManager,
        CtApiCaller $ctApiCaller,
        Kernel $kernel,
        Logger $logger,
        $source
    ) {
        $this->entityManager    = $entityManager;
        $this->ctApiCaller      = $ctApiCaller;
        $this->kernel           = $kernel;
        $this->logger           = $logger;
        $this->source           = $source;
    }

    /**
     * Registers a filter compiler with this service.
     *
     * @param EntityFilterCompiler $filterCompiler
     * @return void
     */
    public function registerEntityFilterCompiler(
        EntityFilterCompiler $filterCompiler
    ) {
        $this->filterCompilers[] = $filterCompiler;
    }

    /**
     * Creates a log entry (but does not persist it).
     *
     * @param string $action
     * @param mixed $affectedEntity
     * @param mixed $parentEntity   If null, $affectedEntity will be used as
     *                              parent.
     * @return ActionLogEntry
     */
    public function createLogEntry(
        $action,
        $affectedEntity,
        ActionLogUserInterface $user = null,
        $parentEntity = null
    ) {
        list(
            $affectedEntityClass,
            $affectedEntityId
        ) = $this->getEntityInfo($affectedEntity);

        if ($parentEntity) {
            list(
                $parentEntityClass,
                $parentEntityId
            ) = $this->getEntityInfo($parentEntity);
        } else {
            $parentEntity = $affectedEntity;
            $parentEntityClass = $affectedEntityClass;
            $parentEntityId = $affectedEntityId;
        }

        // As of now, we only have single-key primary key parent
        // entities. We will throw an exception here if we find
        // multiple keys. This means we added an entity that
        // supports this, and we forgot to update this code.
        if (count($parentEntityId) > 1) {
            throw new \RuntimeException('Multi-key primary key found for parent entity: ' . json_encode($parentEntityId));
        }

        $parentEntityId = current($parentEntityId);

        $parentEntityFilters = $this->getEntityFilters($parentEntity);

        $entry = new ActionLogEntry(
            $action,
            $this->source,
            $affectedEntityClass,
            $affectedEntityId,
            $parentEntityClass,
            $parentEntityId,
            $parentEntityFilters
        );

        if ($user) {
            $entry->setUser($user);
        }

        return $entry;
    }

    /**
     * Send the audit log entry to API to be saved
     * in Mongo.
     *
     * @param ActionLogEntry $entry
     * @return void
     */
    public function persistLogEntry(ActionLogEntry $entry)
    {
        $encodedEntry = json_encode($entry);

        $this->logger->debug("ActionLogger: persist {$encodedEntry}");

        $this->ctApiCaller->post(self::ACTION_LOG_API_PATH, $encodedEntry);
    }

    /**
     * Method used for basic logging without entity
     * change tracking.
     *
     * @param string $action
     * @param mixed $affectedEntity
     * @param mixed $userId
     * @param mixed $parentEntity
     *
     * @return void
     *
     * @throws \Exception
     */
    public function addForEntity(
        $action,
        $affectedEntity,
        ActionLogUserInterface $user = null,
        $parentEntity = null
    ) {
        $logEntry =
            $this
            ->createLogEntry(
                $action,
                $affectedEntity,
                $user,
                $parentEntity
            );
        $this->persistLogEntry($logEntry);
    }

    /**
     * Method used to add to action_log when an entity has
     * been 'tracked' via our EntityManager tracking mechanism.
     * Caller should be passing a valid delta value.
     *
     * @param string $action
     * @param mixed $affectedEntity
     * @param EntityDelta $delta
     * @param mixed $userId
     * @param mixed $parentEntity
     *
     * @return void
     *
     * @throws \Exception
     */
    public function addForEntityDelta(
        $action,
        $affectedEntity,
        EntityDelta $delta,
        ActionLogUserInterface $user = null,
        $parentEntity = null
    ) {
        $logEntry =
            $this
            ->createLogEntry(
                $action,
                $affectedEntity,
                $user,
                $parentEntity
            )
            ->setAffectedEntityDelta($delta);
        $this->persistLogEntry($logEntry);
    }

    /**
     * Returns entity class and ID field/value array.
     *
     * @param mixed $entity
     * @return array [$entityClass, $entityId]
     */
    protected function getEntityInfo($entity)
    {
        $entityClass = Util::shortClassName($entity);

        if (method_exists($entity, 'getEntityId')) {
            // Custom method added to sudo entities. These entities are not
            // managed by Doctrine and therefor don't have annotations.
            $entityId = (array) $entity->getEntityId();
        } else {
            $entityId = $this->entityManager->getEntityId($entity);
        }

        return [$entityClass, $entityId];
    }

    /**
     * Get all the filters related to the given entity.
     *
     * @param $entity
     *
     * @return array
     */
    protected function getEntityFilters($entity)
    {
        $filters = [];

        foreach ($this->filterCompilers as $filterCompiler) {
            if ($filterCompiler->supportsEntity($entity)) {
                $filters = $filterCompiler->compileFilters($entity);
                break;
            }
        }

        return $filters;
    }

}
