<?php
namespace CTLib\Component\ActionLog;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Yaml\Yaml;
use CTLib\Util\Util;
use CTLib\Component\Cache\CompiledVariableCache;
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
     * Log sources.
     * @TODO See if we can remove these.
     */
    const SOURCE_OTP       = 'OTP';
    const SOURCE_CTP       = 'CTP';
    const SOURCE_API       = 'API';
    const SOURCE_INTERFACE = 'IFC';
    const SOURCE_HQ        = 'HQ';

    /**
     * API endpoint for posting action logs.
     */
    const ACTION_LOG_API_PATH = '/actionLogs';

    /**
     * Name of file to cache compiled action set.
     */
    const ACTIONS_CACHE_FILE   = 'actions_cache.php';


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
     * Set of registered source YAML file paths containing the actions.
     * @var array
     */
    protected $actionFiles;

    /**
     * Indicates whether actions have been loaded into memory.
     * @var boolean
     */
    protected $actionsLoaded = false;

    /**
     * Set of actions.
     * @var array
     */
    protected $actions = [];

    /**
     * Set of actions nested within parent groupings.
     * @var array
     */
    protected $groupedActions = [];

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
        $source,
        array $actionFiles
    ) {
        $this->entityManager    = $entityManager;
        $this->ctApiCaller      = $ctApiCaller;
        $this->kernel           = $kernel;
        $this->logger           = $logger;
        $this->source           = $source;
        $this->actionFiles      = $actionFiles;
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
        if (!$this->isValidAction($action)) {
            throw new \InvalidArgumentException("'{$action}' is not a valid action");
        }

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
     * Returns all registered actions.
     *
     * @return array
     */
    public function getActions()
    {
        if (!$this->actionsLoaded) {
            $this->loadActions();
        }
        return $this->actions;
    }

    /**
     * Indicates whether specified action is valid.
     *
     * @param string $action
     * @return boolean
     */
    public function isValidAction($action)
    {
        if (!$this->actionsLoaded) {
            $this->loadActions();
        }
        return in_array($action, $this->actions);
    }

    /**
     * Returns all registered actions nested within parent groupings.
     *
     * @return array
     */
    public function getGroupedActions()
    {
        if (isset($this->groupedActions)) {
            return $this->groupedActions;
        }

        if (!$this->actionsLoaded) {
            $this->loadActions();
        }

        $this->groupedActions = [];

        foreach ($this->actions as $action) {
            $group = explode('.', $action)[0];
            $this->groupedActions[$group][] = $action;
        }

        return $this->groupedActions;
    }

    /**
     * Returns registered actions for specified parent group.
     *
     * @param string $group
     * @return array
     */
    public function getActionsForGroup($group)
    {
        $groupedActions = $this->getGroupedActions();

        if (isset($groupedActions[$group])) {
            return $groupedActions[$group];
        } else {
            return [];
        }
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

    /**
     * Loads registered action codes.
     *
     * @return void
     */
    protected function loadActions()
    {
        $compiler       = new ActionsVariableCompiler();
        $sourcePaths    = $this->getActionsSourcePaths();
        $cachePath      = $this->getActionsCachePath();
        $checkCacheTime = $this->kernel->isDebug();

        $variableCache = new CompiledVariableCache(
            $compiler,
            $sourcePaths,
            $cachePath,
            $checkCacheTime
        );

        $this->actions = $variableCache->getVariable();
        $this->groupedActions = [];
        $this->actionsLoaded = true;
    }

    /**
     * Returns paths of actions source YAML files.
     *
     * @return array
     */
    protected function getActionsSourcePaths()
    {
        $paths = [];

        foreach ($this->actionFiles as $actionFile) {
            $paths[] = $this->kernel->locateResource($actionFile);
        }
        return $paths;
    }

    /**
     * Returns path to action code cache file.
     *
     * @return string
     */
    protected function getActionsCachePath()
    {
        return $this->kernel->getCacheDir() . '/' . self::ACTIONS_CACHE_FILE;
    }

}
