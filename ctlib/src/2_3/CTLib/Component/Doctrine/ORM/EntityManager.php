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
     * Creates DetachedEntityIterator for results and entity.
     *
     * @param array $results    Hydrated as array.
     * @param string $entityName
     *
     * @return DetachedEntityIterator
     */
    public function createDetachedEntityIterator($results, $entityName)
    {
        $metadata = $this
                    ->getEntityMetaHelper()
                    ->getMetadata($entityName);
        return new DetachedEntityIterator($results, $metadata);
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
                    $entity->{"set{$fieldName}"}($value);
                    break;
                case 'effectiveTime':
                    if ($entity->hasExplicitEffectiveTime()) {
                        $value = $entity->getEffectiveTime();
                    } else {
                        $value = time();
                    }
                    $entity->{"set{$fieldName}"}($value);
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
            $entity->{$setter}((int) $this->getConnection()->lastInsertId());
        }
    }

    /**
     * Updates entity's database record.
     *
     * NOTE: This method does not use Doctrine's UnitOfWork nor does it make
     *       the entity managed by the EntityManager.
     *
     * @param Entity $entity
     * @return void
     */
    public function update($entity)
    {
        $metaData   = $this->getEntityMetaHelper()->getMetadata($entity);
        $tableName  = $metaData->getTableName();
        $fields     = array_flip($metaData->fieldNames); // We need field => column
        $entityId   = $this->getEntityId($entity);
        $values     = [];
        $identifier = [];

        // Iterate through all of entity's fields to build a columnName => value
        // map. We'll use this map to run Connection#update.
        foreach ($fields as $fieldName => $columnName) {
            if (isset($entityId[$fieldName])) {
                // Field won't be part of update values because it's the entity's
                // ID.
                $idValue = $entityId[$fieldName];
                $identifier[$columnName] = $idValue;
                continue;
            }

            if ($fieldName == 'modifiedOn') {
                // Do a solid by automatically setting modifiedOn.
                $value = time();
            } else {
                // Retrieve the field's value currently set in the entity.
                $getter = "get{$fieldName}";
                $value  = $entity->{$getter}();
            }

            $values[$columnName] = $value;
        }
        
        $this
            ->getConnection()
            ->update($tableName, $values, $identifier);
    }

    /**
     * Updates entity's database record and object for specified update fields.
     * 
     * NOTE: This method does not use Doctrine's UnitOfWork nor does it make
     *       the entity managed by the EntityManager.
     * 
     * @param  Entity $entity       
     * @param  array $updateFields [$fieldName => $newValue, ...]
     * 
     * @return void               
     */
    public function updateForFields($entity, $updateFields)
    {
        $metaData   = $this->getEntityMetaHelper()->getMetadata($entity);
        $tableName  = $metaData->getTableName();
        $fields     = array_flip($metaData->fieldNames); // We need field => column
        $entityId   = $this->getEntityId($entity);
        $values     = [];
        $identifier = [];

        if (isset($fields['modifiedOn'])
            && ! isset($updateFields['modifiedOn'])) {
            // Do a solid and automatically set modifiedOn when it hasn't been
            // explicitly set by the user.
            $updateFields['modifiedOn'] = time();
        }

        // Iterate through each updated value and build columnName => value map
        // so we can use Connection#update. Also update the entity with the
        // changed value.
        foreach ($updateFields as $fieldName => $value) {
            if (! isset($fields[$fieldName])) {
                throw new \RuntimeException("Field '{$fieldName}' does not exist for " . get_class($entity));
            }

            // Convert to columnName and add to value map.
            $columnName = $fields[$fieldName];
            $values[$columnName] = $value;

            // Update the entity with the changed value.
            $setter = "set{$fieldName}";
            $entity->{$setter}($value);
        }
    
        // Convert entity's ID to columnName => $value map for use with
        // Connection#update.
        foreach ($entityId as $fieldName => $value) {
            $columnName = $fields[$fieldName];
            $identifier[$columnName] = $value;
        }

        $this
            ->getConnection()
            ->update($tableName, $values, $identifier);
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
     * Checks whether a transaction is currently active.
     *
     * @return boolean TRUE if a transaction is currently active, FALSE otherwise.
     */
    public function isTransactionActive()
    {
        return $this->getConnection()->isTransactionActive();
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
