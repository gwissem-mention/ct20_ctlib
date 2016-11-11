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
     * "Member ID" used to record system actions.
     * @TODO See if we can remove this since we have it in ActionLogEntry.
     */
    const SYSTEM_MEMBER_ID   = 0;

    /**
     * API endpoint for posting action logs.
     */
    const AUDIT_LOG_API_PATH = '/actionLogs';

    /**
     * Name of file to cache compiled action codes.
     */
    const ACTION_CODES_CACHE_FILE   = 'actionCodes_cache.php';


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
     * Set of registered source YAML file paths containing the action codes.
     * @var array
     */
    protected $actionCodeFiles;

    /**
     * Indicates whether action codes have been loaded into memory.
     * @var boolean
     */
    protected $actionCodesLoaded = false;

    /**
     * Set of action codes in form [$actionName => $actionCode, ...]
     * @var array
     */
    protected $actionCodes = [];

    /**
     * Set of action codes nested within parent grouping.
     * @var array
     */
    protected $groupedActionCodes = [];

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
     * @param array $actionCodeFiles
     */
    public function __construct(
        EntityManager $entityManager,
        CtApiCaller $ctApiCaller,
        Kernel $kernel,
        Logger $logger,
        $source,
        array $actionCodeFiles
    ) {
        $this->entityManager        = $entityManager;
        $this->ctApiCaller          = $ctApiCaller;
        $this->kernel               = $kernel;
        $this->logger               = $logger;
        $this->source               = $source;
        $this->actionCodeFiles      = $actionCodeFiles;
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
     * @param string $actionName
     * @param mixed $affectedEntity
     * @param mixed $parentEntity   If null, $affectedEntity will be used as
     *                              parent.
     * @return ActionLogEntry
     */
    public function createLogEntry(
        $actionName,
        $affectedEntity,
        $parentEntity = null
    ) {
        if (!$this->isValidActionName($actionName)) {
            throw new \InvalidArgumentException("'{$actionName}' is not a valid action");
        }

        $actionCode = $this->getActionCodeForName($actionName);

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

        return new ActionLogEntry(
            $actionCode,
            $this->source,
            $affectedEntityClass,
            $affectedEntityId,
            $parentEntityClass,
            $parentEntityId,
            $parentEntityFilters
        );
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

        $this->ctApiCaller->post(self::AUDIT_LOG_API_PATH, $encodedEntry);
    }

    /**
     * Method used for basic logging without entity
     * change tracking.
     *
     * @param string $actionName
     * @param mixed $affectedEntity
     * @param mixed $userId
     * @param mixed $parentEntity
     *
     * @return void
     *
     * @throws \Exception
     */
    public function addForEntity(
        $actionName,
        $affectedEntity,
        $userId = null,
        $parentEntity = null
    ) {
        $logEntry =
            $this->createLogEntry($actionName, $affectedEntity, $parentEntity);

        if ($userId) {
            $logEntry->setUserId($userId);
        }

        $this->persistLogEntry($logEntry);
    }

    /**
     * Method used to add to action_log when an entity has
     * been 'tracked' via our EntityManager tracking mechanism.
     * Caller should be passing a valid delta value.
     *
     * @param string $actionName
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
        $actionName,
        $affectedEntity,
        EntityDelta $delta,
        $userId = null,
        $parentEntity = null
    ) {
        $logEntry =
            $this->createLogEntry($actionName, $affectedEntity, $parentEntity);

        $logEntry->setAffectedEntityDelta($delta);

        if ($userId) {
            $logEntry->setUserId($userId);
        }

        $this->persistLogEntry($logEntry);
    }

    /**
     * Returns action code mapped to name.
     *
     * @param string $actionName
     * @return integer|null
     */
    public function getActionCodeForName($actionName)
    {
        if (!$this->actionCodesLoaded) {
            $this->loadActionCodes();
        }

        if (isset($this->actionCodes[$actionName])) {
            return $this->actionCodes[$actionName];
        } else {
            return null;
        }
    }

    /**
     * Indicates whether action name is registered.
     *
     * @param string $actionName
     * @return boolean
     */
    public function isValidActionName($actionName)
    {
        return $this->getActionCodeForName($actionName) ? true : false;
    }

    /**
     * Returns action name mapped to code.
     *
     * @param integer $actionCode
     * @return string|null
     */
    public function getNameForActionCode($actionCode)
    {
        if (!$this->actionCodesLoaded) {
            $this->loadActionCodes();
        }

        $actionName = array_search($actionCode, $this->actionCodes);
        return $actionName ?: null;
    }

    /**
     * Indicates whether action code is registered.
     *
     * @param integer $actionCode
     * @return boolean
     */
    public function isValidActionCode($actionCode)
    {
        return $this->getNameForActionCode($actionCode) ? true : false;
    }

    /**
     * Returns all registered action codes.
     *
     * @return array  [$actionName => $actionCode, ...]
     */
    public function getActionCodes()
    {
        if (!$this->actionCodesLoaded) {
            $this->loadActionCodes();
        }

        return $this->actionCodes;
    }

    /**
     * Returns all registered action codes nested within parent group.
     *
     * @return array
     */
    public function getGroupedActionCodes()
    {
        if (isset($this->groupedActionCodes)) {
            return $this->groupedActionCodes;
        }

        if (!$this->actionCodesLoaded) {
            $this->loadActionCodes();
        }

        $this->groupedActionCodes = [];

        foreach ($this->actionCodes as $actionName => $actionCode) {
            list($group, $name) = explode('.', $actionName);
            $this->groupedActionCodes[$group][$actionName] = $actionCode;
        }

        return $this->groupedActionCodes;
    }

    /**
     * Returns registered action codes for specified parent group.
     *
     * @param string $group
     * @return array [$actionName => $actionCode, ...]
     */
    public function getActionCodesForGroup($group)
    {
        $groupedActionCodes = $this->getGroupedActionCodes();

        if (isset($groupedActionCodes[$group])) {
            return $groupedActionCodes[$group];
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
    protected function loadActionCodes()
    {
        $compiler       = new ActionCodesVariableCompiler();
        $sourcePaths    = $this->getActionCodesSourcePaths();
        $cachePath      = $this->getActionCodesCachePath();
        $checkCacheTime = $this->kernel->isDebug();

        $variableCache = new CompiledVariableCache(
            $compiler,
            $sourcePaths,
            $cachePath,
            $checkCacheTime
        );

        $this->actionCodes = $variableCache->getVariable();
        $this->groupedActionCodes = [];
        $this->actionCodesLoaded = true;
    }

    /**
     * Returns paths of action code source YAML files.
     *
     * @return array
     */
    protected function getActionCodesSourcePaths()
    {
        $paths = [];

        foreach ($this->actionCodeFiles as $actionCodeFile) {
            $paths[] = $this->kernel->locateResource($actionCodeFile);
        }
        return $paths;
    }

    /**
     * Returns path to action code cache file.
     *
     * @return string
     */
    protected function getActionCodesCachePath()
    {
        return $this->kernel->getCacheDir()
            . '/' . self::ACTION_CODES_CACHE_FILE;
    }

}
