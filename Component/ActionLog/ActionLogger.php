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
    const SOURCE_OTP       = 'OTP';
    const SOURCE_CTP       = 'CTP';
    const SOURCE_API       = 'API';
    const SOURCE_INTERFACE = 'IFC';
    const SOURCE_HQ        = 'HQ';

    const SYSTEM_MEMBER_ID   = 0;

    const AUDIT_LOG_API_PATH = '/actionLogs';

    const ACTION_CODES_CACHE_FILE   = 'actionCodes_cache.php';

    /**
     * @var CtApiCaller
     */
    protected $ctApiCaller;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var string
     */
    protected $source;

    /**
     * @var array
     */
    protected $filterCompilers = [];

    protected $actionCodeFiles;

    protected $actionCodesLoaded;

    protected $actionCodes;

    protected $groupedActionCodes;


    /**
     * @param EntityManager $entityManager
     * @param CtApiCaller $ctApiCaller
     * @param string $source
     */
    public function __construct(
        EntityManager $entityManager,
        CtApiCaller $ctApiCaller,
        Kernel $kernel,
        $source,
        array $actionCodeFiles,
        Logger $logger
    ) {
        $this->entityManager        = $entityManager;
        $this->ctApiCaller          = $ctApiCaller;
        $this->kernel               = $kernel;
        $this->source               = $source;
        $this->actionCodeFiles      = $actionCodeFiles;
        $this->logger               = $logger;
        $this->actionCodesLoaded    = false;
        $this->actionCodes          = [];
    }

    /**
    * Register a filter compiler with this service.
    *
    * @param EntityFilterCompiler $filterCompiler
    */
    public function registerEntityFilterCompiler(
        EntityFilterCompiler $filterCompiler
    ) {
        $this->filterCompilers[] = $filterCompiler;
    }

    public function createLogEntry(
        $actionName,
        $affectedEntity,
        $parentEntity = null
    ) {
        if (!$this->isValidActionName($actionName)) {
            throw new \InvalidArgumentException("'{$actionName}' is not a valid action");
        }

        $actionCode = $this->getActionCodeForName($actionName);

        if (!$parentEntity) {
            $parentEntity = $affectedEntity;
        }

        list(
            $affectedEntityId,
            $affectedEntityClass
        ) = $this->getEntityInfo($affectedEntity);

        list(
            $parentEntityId,
            $parentEntityClass
        ) = $this->getEntityInfo($parentEntity);

        // As of now, we only have single-key primary key parent
        // entities. We will throw an exception here if we find
        // multiple keys. This means we added an entity that
        // supports this, and we forgot to update this code.
        if (count($parentEntityId) > 1) {
            throw new \RuntimeException('Multi-key primary key found for parent entity: '.json_encode($parentEntityId));
        }

        $parentEntityId = current($parentEntityId);

        $parentEntityFilters = $this->getEntityFilters($parentEntity);

        return new ActionLogEntry(
            $actionCode,
            $this->source,
            $parentEntityClass,
            $parentEntityId,
            $parentEntityFilters,
            $affectedEntityClass,
            $affectedEntityId
        );
    }

    /**
     * Send the audit log entry to API to be saved
     * in Mongo.
     *
     * @param string $log
     *
     * @return void
     */
    public function persistLogEntry(ActionLogEntry $entry)
    {
        print("\n");
        print(json_encode($entry));
        print("\n");
        return;

        $this->ctApiCaller->post(
            self::AUDIT_LOG_API_PATH,
            json_encode($entry)
        );
    }

    /**
     * Method used for basic logging without entity
     * change tracking.
     *
     * @param int $action
     * @param $entity
     * @param $parentEntity
     * @param int $memberId
     * @param string $comment
     *
     * @return void
     *
     * @throws \Exception
     */
    public function addForEntity(
        $actionName,
        $affectedEntity,
        $memberId = ActionLogEntry::SYSTEM_MEMBER_ID,
        $parentEntity = null
    ) {
        $logEntry =
            $this->createLogEntry($actionName, $affectedEntity, $parentEntity);

        if ($memberId) {
            $logEntry->setMemberId($memberId);
        }

        $this->persistLogEntry($logEntry);
    }

    /**
     * Method used to add to action_log when an entity has
     * been 'tracked' via our EntityManager tracking mechanism.
     * Caller should be passing a valid delta value.
     *
     * @param int $action
     * @param $entity
     * @param EntityDelta $delta
     * @param $parentEntity
     * @param int $memberId
     * @param string $comment
     *
     * @return void
     *
     * @throws \Exception
     */
    public function addForEntityDelta(
        $actionName,
        $affectedEntity,
        EntityDelta $delta,
        $memberId = self::SYSTEM_MEMBER_ID,
        $parentEntity = null
    ) {
        $logEntry =
            $this->createLogEntry($actionName, $affectedEntity, $parentEntity);

        $logEntry->setAffectedEntityDelta($delta);

        if ($memberId) {
            $logEntry->setMemberId($memberId);
        }

        $this->persistLogEntry($logEntry);
    }

    public function getNameForActionCode($actionCode)
    {
        if (!$this->actionCodesLoaded) {
            $this->loadActionCodes();
        }

        return array_search($actionCode, $this->actionCodes);
    }

    public function isValidActionCode($actionCode)
    {
        return $this->getNameForActionCode($actionCode) ? true : false;
    }

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

    public function isValidActionName($actionName)
    {
        return $this->getActionCodeForName($actionName) ? true : false;
    }

    public function getActionCodes()
    {
        if (!$this->actionCodesLoaded) {
            $this->loadActionCodes();
        }

        return $this->actionCodes;
    }

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

    public function getActionCodesForGroup($group)
    {
        $groupedActionCodes = $this->getGroupedActionCodes();

        if (isset($groupedActionCodes[$group])) {
            return $groupedActionCodes[$group];
        } else {
            return [];
        }
    }

    protected function getEntityInfo($entity)
    {
        $entityId = $this->entityManager->getEntityId($entity);

        $entityClass = $this
            ->entityManager
            ->getEntityMetaHelper()
            ->getShortClassName($entity);

        return [$entityId, $entityClass];
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

    protected function getActionCodesSourcePaths()
    {
        $paths = [];

        foreach ($this->actionCodeFiles as $actionCodeFile) {
            $paths[] = $this->kernel->locateResource($actionCodeFile);
        }
        return $paths;
    }

    protected function getActionCodesCachePath()
    {
        return $this->kernel->getCacheDir()
            . '/' . self::ACTION_CODES_CACHE_FILE;
    }


}
