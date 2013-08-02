<?php
namespace CTLib\Component\Doctrine\ORM;

use Doctrine\Common\EventManager,
    Doctrine\ORM\Configuration,
    CTLib\Helper\EntityMetaHelper;

/**
 * CellTrak extended EntityManager class.
 */
class EntityManager extends \Doctrine\ORM\EntityManager
{
    /**
     * @var QueryMetaMapCache
     */
    protected $queryMetaMapCache;

    /**
     * @var string
     */
    protected $defaultBundleName;

    /**
     * @var EntityMetaHelper
     */
    protected $entityMetaHelper;


    /**
     * Injects QueryMetaMapCache service into EntityManager.
     *
     * Used by InjectIntoEntityManager listener.
     *
     * @param QueryMetaMapCache $queryMetaMapCache 
     *
     * @return void
     */
    public function setQueryMetaMapCache($queryMetaMapCache)
    {
        $this->queryMetaMapCache = $queryMetaMapCache;
    }

    /**
     * Returns QueryMetaMapCache.
     *
     * @return QueryMetaMapCache
     */
    public function getQueryMetaMapCache()
    {
        return $this->queryMetaMapCache;
    }

    /**
     * Creates new QueryBuilder instance.
     *
     * Overrides default method so it will create instance of our custom
     * QueryBuilder class.
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this);
    }

    /**
     * Returns a new DataProviderQueryBuilder instance.
     *
     * @return DataProviderQueryBuilder
     */
    public function createDataProviderQueryBuilder()
    {
        return new DataProviderQueryBuilder($this);
    }

    /**
     * Returns QueryMetaMap for specified $queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     *
     * @return QueryMetaMap
     */
    public function getQueryMetaMap($queryBuilder)
    {
        return $this->queryMetaMapCache->getMap($queryBuilder);
    }

    /**
     * Returns EntityMetaHelper.
     *
     * @return EntityMetaHelper
     */
    public function getEntityMetaHelper()
    {
        if (! isset($this->entityMetaHelper)) {
            $this->entityMetaHelper = new EntityMetaHelper($this);
        }
        return $this->entityMetaHelper;
    }

    /**
     * Returns entity name prepended with default bundle name if needed.
     *
     * @param string $entityName
     *
     * @return string
     */
    public function qualifyEntityName($entityName)
    {
        if (strpos($entityName, 'Bundle')) { return $entityName; }

        if (! isset($this->defaultBundleName)) {
            $namespaces = $this->getConfiguration()->getEntityNamespaces();
            $this->defaultBundleName = current(array_keys($namespaces));
        }
        return "{$this->defaultBundleName}\Entity\\$entityName";
    }

    /**
     * Persists all passed entities and then flushes afterward.
     *
     * @param Entity $entity ,...
     *
     * @return void
     */
    public function save($entity)
    {
        $entities = func_get_args();
        foreach ($entities as $entity) {
            $this->persist($entity);
        }
        $this->flush();
    }

    /**
     * Inserts an entity directly into the database.
     *
     * Mike T. @ 7/9/2013: This method should be considered experimental only.
     *
     * NOTE: This method does not use Doctrine's UnitOfWork nor does it make
     *       the entity managed by the EntityManager.
     *
     * @param Entity $entity
     * @return void
     */
    public function insert($entity)
    {
    	$meta = $this->getEntityMetaHelper()->getMetadata($entity);

        if (isset($meta->lifecycleCallbacks)
            && isset($meta->lifecycleCallbacks['prePersist'])) {
            foreach ($meta->lifecycleCallbacks['prePersist'] as $method) {
                $entity->{$method}();
            }
        }

        $fields = $meta->fieldNames;
        $values = array();

        foreach ($fields as $columnName => $fieldName) {
            switch ($fieldName) {
                case 'addedOn':
                case 'modifiedOn':
                    $value = time();
                    break;
                case 'effectiveTime':
                    if ($entity->hasExplicitEffectiveTime()) {
                        $value = $entity->getEffectiveTime();
                    } else {
                        $value = time();
                    }
                    break;
                default:
                    $getter = "get{$fieldName}";
                    $value  = $entity->{$getter}();
                    break;
            }

            if (! is_null($value)) {
                $values[$columnName] = $value;
            }
        }

        $this
            ->getConnection()
            ->insert($meta->getTableName(), $values);

        if ($meta->isIdGeneratorIdentity() || $meta->isIdGeneratorSequence()) {
            $idFieldName    = current($meta->getIdentifier());
            $setter         = "set{$idFieldName}";
            $entity->{$setter}($this->getConnection()->lastInsertId());
        }
    }

    /**
     * Updates entity's database record.
     *
     * Mike T. @ 7/9/2013: This method should be considered experimental only.
     *
     * NOTE: This method does not use Doctrine's UnitOfWork nor does it make
     *       the entity managed by the EntityManager.
     *
     * @param Entity $entity
     * @return void
     */
    public function update($entity)
    {
	    $meta   = $this->getEntityMetaHelper()->getMetadata($entity);
        $fields = $meta->fieldNames;
        $values = array();
        $id     = $this->getEntityId($entity);
        $useId  = array();

        foreach ($fields as $columnName => $fieldName) {
            if (isset($id[$fieldName])) {
                $useId[$columnName] = $id[$fieldName];
                continue;
            }

            if ($fieldName == 'modifiedOn') {
                $value = time();
            } else {
                $getter = "get{$fieldName}";
                $value  = $entity->{$getter}();
            }
            $values[$columnName] = $value;
        }

        $this
            ->getConnection()
            ->update($meta->getTableName(), $values, $useId);
    }

    /**
     * Returns entity's id fields with their values.
     *
     * @param Entity $entity
     *
     * @return array            array($idFieldName => $idValue, ...)
     */
    public function getEntityId($entity)
    {
        $idFieldNames = $this->getEntityMetaHelper()
                            ->getIdentifierFieldNames(get_class($entity));
        $id = array();
        foreach ($idFieldNames as $idFieldName) {
            $idFieldValue = $entity->{"get{$idFieldName}"}();
            if ($idFieldValue instanceof \CTLib\Entity\BaseEntity) {
                $id += $this->getEntityId($idFieldValue);
            } else {
                $id[$idFieldName] = $idFieldValue;    
            }
        }
        return $id;
    }

    /**
     * Returns entity's logical (non-effective) id fields with their values.
     *
     * @param Entity $entity
     *
     * @return array            array($idFieldName => $idValue, ...)
     */
    public function getEntityLogicalId($entity)
    {
        $idFieldNames = $this->getEntityMetaHelper()
                            ->getLogicalIdentifierFieldNames(get_class($entity));
        $id = array();
        foreach ($idFieldNames as $idFieldName) {
            $idFieldValue = $entity->{"get{$idFieldName}"}();
            if ($idFieldValue instanceof \CTLib\Entity\BaseEntity) {
                $id += $this->getEntityLogicalId($idFieldValue);
            } else {
                $id[$idFieldName] = $idFieldValue;
            }
        }
        return $id;
    }

    /**
     * Begins a new transation.
     *
     * @return void
     */
    public function beginTransaction()
    {
        $this->getConnection()->beginTransaction();
    }

    /**
     * Commits current transaction.
     *
     * @return void
     */
    public function commitTransaction()
    {
        $this->getConnection()->commit();
    }

    /**
     * Rolls back current transaction.
     *
     * @param boolean $close  If true, will close this EntityManager after rollback.
     *
     * @return void
     */
    public function rollbackTransaction($close=false)
    {
        $this->getConnection()->rollback();
        if ($close) {
            $this->close();
        }
    }

    /**
     * Creates a new EntityManager using this one as the source for connection
     * and configuration.
     *
     * @return EntityManager
     */
    public function createPeer()
    {
        $peer = new self(
            $this->getConnection(),
            $this->getConfiguration(),
            $this->getEventManager()
        );
        $peer->setQueryMetaMapCache($this->getQueryMetaMapCache());
        return $peer;
    }

    /**
     * Refinds same entity. Useful when EntityManager had to be replaced.
     *
     * @param Entity $entity
     * @return Entity
     */
    public function refind($entity)
    {
        $id = $this->getEntityId($entity);
        return $this->getRepository(get_class($entity))->find($id);
    }

    /**
     * Factory method to create EntityManager instances.
     *
     * Overrides default method so it creates instance of our custom
     * EntityManager class.
     *
     * @param mixed $conn An array with the connection parameters or an existing
     *      Connection instance.
     * @param Configuration $config The Configuration instance to use.
     * @param EventManager $eventManager The EventManager instance to use.
     *
     * @return EntityManager The created EntityManager.
     */
    public static function create($conn, Configuration $config, EventManager $eventManager = null)
    {
        $em = parent::create($conn, $config, $eventManager);
        return self::createFromPeer($em);
    }

    /**
     * Creates new EntityManager from peer EntityManager.
     *
     * @param EntityManager $peerEm
     *
     * @return EntityManager
     */
    public static function createFromPeer($peerEm)
    {
        $em = new EntityManager(
            $peerEm->getConnection(),
            $peerEm->getConfiguration(),
            $peerEm->getEventManager()
        );

        if (method_exists($peerEm, 'getQueryMetaMapCache')) {
            $em->setQueryMetaMapCache($peerEm->getQueryMetaMapCache());
        }
        return $em;
    }

}
