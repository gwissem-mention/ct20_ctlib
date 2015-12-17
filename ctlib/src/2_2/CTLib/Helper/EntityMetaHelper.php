<?php
namespace CTLib\Helper;

use CTLib\Util\Util;

class EntityMetaHelper
{
    protected $entityManager;

    
    /**
     * @param EntityManager $entityManager
     */
    public function __construct($entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Returns entity's metadata.
     *
     * @param mixed $entity
     * @return ClassMetadata
     */
    public function getMetadata($entity)
    {
        $entityName = is_string($entity) ? $entity : get_class($entity);
        return $this->entityManager->getClassMetadata($entityName);
    }

    /**
     * Returns entity's class name.
     *
     * @param mixed $entity
     * @return string
     */
    public function getClassName($entity)
    {
        return $this->getMetadata($entity)->name;
    }

    /**
     * Returns entity's class name without namespacing.
     *
     * @param mixed $entity
     * @return string
     */
    public function getShortClassName($entity)
    {
        return Util::shortClassName($this->getClassName($entity));
    }

    /**
     * Returns entity's table name.
     *
     * @param mixed $entity
     * @return string
     */
    public function getTableName($entity)
    {
        return $this->getMetadata($entity)->getTableName();
    }

    /**
     * Indicates whether entity is effective.
     *
     * @param mixed $entity
     * @return boolean
     */
    public function isEffective($entity)
    {
        return is_subclass_of(
            $this->getClassName($entity),
            'CTLib\Entity\EffectiveEntity'
        );
    }

    /**
     * Returns entity's field names.
     *
     * @param mixed $entity
     * @return array
     */
    public function getFieldNames($entity)
    {
        return $this->getMetadata($entity)->getFieldNames();
    }

    /**
     * Returns entity's identifier field names.
     *
     * @param mixed $entity
     * @return array
     */
    public function getIdentifierFieldNames($entity)
    {
        return $this->getMetadata($entity)->getIdentifierFieldNames();
    }

    /**
     * Returns entity's column names.
     *
     * @param mixed $entity
     * @return array
     */
    public function getIdentifierColumnNames($entity)
    {
        return $this->getMetadata($entity)->getIdentifierColumnNames();
    }

    /**
     * Returns entity's logical (non-effective) identifier field names.
     *
     * @param mixed $entity
     * @return array
     */
    public function getLogicalIdentifierFieldNames($entity)
    {
        $idFieldNames = $this->getIdentifierFieldNames($entity);

        if (! $this->isEffective($entity)) { return $idFieldNames; }

        $effectiveTimePosition = array_search('effectiveTime', $idFieldNames);
    
        if ($effectiveTimePosition === false) { return $idFieldNames; }
        
        // effectiveTime doesn't belong in logical ID fields.
        unset($idFieldNames[$effectiveTimePosition]);
        return $idFieldNames;
    }

    /**
     * Returns entity's logical (non-effective) identifier column names.
     *
     * @param mixed $entity
     * @return array
     */
    public function getLogicalIdentifierColumnNames($entity)
    {
        $idColNames = $this->getIdentifierColumnNames($entity);

        if (! $this->isEffective($entity)) { return $idColNames; }

        $effectiveTimePosition = array_search('effective_time', $idColNames);
    
        if ($effectiveTimePosition === false) { return $idColNames; }
        
        // effectiveTime doesn't belong in logical ID fields.
        unset($idColNames[$effectiveTimePosition]);
        return $idColNames;
    }

    /**
     * Returns entity's effective schema used to build effective query.
     *
     * @param mixed $entity
     *
     * @return array
     * @throws Exception    If entity is not effective.
     */
    public function getEffectiveSchema($entity)
    {
        if (! $this->isEffective($entity)) {
            throw new \Exception("Non-effective entity: $entity");
        }

        return array(
            'table' => $this->getTableName($entity),
            'idColumns' => $this->getLogicalIdentifierColumnNames($entity)
        );   
    }

    /**
     * Returns entity's association mapping.
     *
     * @param string $parentEntityName
     * @param string $associationName
     *
     * @return array
     */
    public function getAssociationMapping($parentEntityName, $associationName)
    {
        return $this->getMetadata($parentEntityName)
            ->getAssociationMapping($associationName);
    }

    /**
     * Returns class name of an entity's association.
     *
     * @param string $parentEntityName
     * @param string $associationName
     *
     * @return string
     * @throws Exception    If association not found.
     */
    public function getAssociationClassName($parentEntityName, $associationName)
    {
        $associationMapping = $this->getAssociationMapping(
            $parentEntityName,
            $associationName
        );

        if (! $associationMapping) {
            throw new \Exception("Invalid association mapping named '$parentAssociationName' for $parentEntityName.");
        }

        return $associationMapping['targetEntity'];
    }

    /**
     * convert aliased entity name into real entity name with full namespace
     *
     * @param string $className entity name
     * @return string real entity class name with full namespace
     *
     */
    public function getRealEntityClass($className)
    {
        if (strpos($className, ':') !== false) {
            list($namespaceAlias, $simpleClassName) = explode(':', $className);
            $realClassName = $this->entityManager->getConfiguration()->getEntityNamespace($namespaceAlias) . '\\' . $simpleClassName;
        } else {
            $realClassName = \Doctrine\Common\Util\ClassUtils::getRealClass($className);
        }
        return $realClassName;
    }
}