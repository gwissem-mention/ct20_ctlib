<?php
namespace CTLib\Component\QueryMetaMap;

use CTLib\Helper\EntityMetaHelper;



class QueryMetaMap
{
    protected $entities;
    protected $selectSources;


    /**
     * @param QueryBuilder $queryBuilder
     */
    public function __construct()
    {
        $this->entities         = array();
        $this->selectSources    = array();
    }

    /**
     * Parses set QueryBuilder into mapped components.
     *
     * @param QueryBuilder $queryBuilder
     * @return void
     */
    public function parse($queryBuilder)
    {
        $queryAst         = $queryBuilder->getQuery()->getAST();
        $entityMetaHelper = $queryBuilder->getEntityManager()->getEntityMetaHelper();

        $this->parseEntities($queryAst, $entityMetaHelper);
        $this->parseSelectSources($queryAst);
    }

    /**
     * Parse entities used in query.
     *
     * @param Ast               $queryAst
     * @param EntityMetaHelper  $entityMetaHelper
     *
     * @return void
     */
    protected function parseEntities($queryAst, $entityMetaHelper)
    {
        foreach ($queryAst->fromClause->identificationVariableDeclarations as $rootAst) {
            $entity = $this->createRootEntity($rootAst, $entityMetaHelper);
            $this->addEntity($entity);

            // Parse join entities.
            if (isset($rootAst->joins)) {
                foreach ($rootAst->joins AS $joinAst) {
                    $entity = $this->createJoinEntity(
                        $joinAst,
                        $entityMetaHelper
                    );
                    $this->addEntity($entity);
                }
            }
        }
    }

    /**
     * Creates root entity meta definition.
     *
     * @param Ast               $rootAst
     * @param EntityMetaHelper  $entityMetaHelper
     *
     * @return object $entity
     */
    protected function createRootEntity($rootAst, $entityMetaHelper)
    {
        $entity = new \stdClass;
        $entity->name = $rootAst->rangeVariableDeclaration->abstractSchemaName;
        $entity->alias = $rootAst->rangeVariableDeclaration->aliasIdentificationVariable;
        $entity->fieldNames = $entityMetaHelper->getFieldNames($entity->name);
        $entity->route = array();
        return $entity;
    }

    /**
     * Creates join entity meta definition.
     *
     * @param Ast               $joinAst
     * @param EntityMetaHelper  $entityMetaHelper
     *
     * @return object
     */
    protected function createJoinEntity($joinAst, $entityMetaHelper)
    {
        $joinAsscDcl            = $joinAst->joinAssociationDeclaration;
        $alias                  = $joinAsscDcl->aliasIdentificationVariable;
        $joinType               = $joinAst->joinType;
        $parentAlias            = $joinAsscDcl
                                    ->joinAssociationPathExpression
                                    ->identificationVariable;
        $parentAssociationName  = $joinAsscDcl
                                    ->joinAssociationPathExpression
                                    ->associationField;

        $parentEntity = $this->getEntity($parentAlias);

        if (! $parentEntity) {
            throw new \Exception("Parent entity not found for alias: $parentAlias.");
        }

        $entity = new \stdClass;
        $entity->name = $entityMetaHelper->getAssociationClassName(
            $parentEntity->name,
            $parentAssociationName
        );
        $entity->alias      = $alias;
        $entity->fieldNames = $entityMetaHelper->getFieldNames($entity->name);
        $entity->route      = $parentEntity->route;
        $entity->route[]    = array(
            'alias'           => $parentAlias,
            'associationName' => $parentAssociationName,
            'joinType'        => $joinType,
            'isEffective'     => $entityMetaHelper->isEffective($entity->name)
        );
        return $entity;
    }

    /**
     * Parses value sources of SELECT clause used in query.
     *
     * @return void
     */
    protected function parseSelectSources($queryAst)
    {
        foreach ($queryAst->selectClause->selectExpressions AS $selectAst) {
            $expressionAst = $selectAst->expression;

            if (is_string($expressionAst)) {
                // Selecting complete entity.
                $this->addEntireEntityToSelect($expressionAst);
            } elseif (isset($expressionAst->partialFieldSet)) {
                // Selecting partial entity.
                $entityAlias = $expressionAst->identificationVariable;
                foreach ($expressionAst->partialFieldSet AS $fieldName) {
                    $this->addSelectSource("$entityAlias.$fieldName");
                }
            } else {
                // Selecting individual field.
                if (isset($selectAst->fieldIdentificationVariable)) {
                    // Using "e.field AS 'alias'".
                    $this->addSelectSource(
                        $selectAst->fieldIdentificationVariable
                    );
                } elseif (isset($expressionAst->field)) {
                    // Using "e.field".
                    $this->addSelectSource($expressionAst->field);
                } else {
                    // Using an un-supported select source (probably an
                    // aggreagate function without alias).  Currently, just
                    // ignore this select source.
                }
            }
        }
    }

    /**
     * Adds all fields in entity to select sources.
     *
     * @param string $alias
     * @return void
     */
    protected function addEntireEntityToSelect($alias)
    {
        $entity = $this->getEntity($alias);

        if (! $entity) {
            throw new \Exception("Entity not found for alias: $alias.");
        }

        foreach ($entity->fieldNames AS $fieldName) {
            $this->addSelectSource("$alias.$fieldName");
        }
    }

    /**
     * Adds entity meta object into map.
     *
     * @param object $entity
     *
     * @return void
     * @throws Exception    If entity's alias already exists in map.
     */
    protected function addEntity($entity)
    {
        if (isset($this->entities[$entity->alias])) {
            throw new \Exception("Entity for alias: {$entity->alias} exists.");
        }
        $this->entities[$entity->alias] = $entity;
    }

    /**
     * Adds select source into map.
     *
     * @param string $source
     * @return void
     */
    protected function addSelectSource($source)
    {
        $this->selectSources[] = $source;
    }

    /**
     * Returns meta object for specified entity alias.
     *
     * @param string $alias
     * @return mixed    Returns meta object or NULL if alias not found.
     */
    public function getEntity($alias)
    {
        return isset($this->entities[$alias]) ? $this->entities[$alias] : null;
    }

    /**
     * Returns meta object for specified entity alias. Throws Exception if
     * alias not found.
     *
     * @param string $alias
     * @return stdObject
     */
    public function mustGetEntity($alias)
    {
        $entity = $this->getEntity($alias);
        if (! $entity) {
            throw new \Exception("Entity not found for alias: $alias");
        }
        return $entity;
    }

    /**
     * Indicates whether query includes entity specified by its alias.
     *
     * @param string $alias
     * @return boolean
     */
    public function hasEntity($alias)
    {
        return ! is_null($this->getEntity($alias));
    }

    /**
     * Returns entity based on its full name ("Bundle\Entity\EntityName").
     *
     * @param string $name
     * @return mixed    Returns meta object or NULL if name not found.
     */
    public function getEntityForName($name)
    {
        foreach ($this->entities AS $entity) {
            if ($entity->name == $name) {
                return $entity;
            }
        }
        return null;
    }

    /**
     * Returns all entity meta objects involved in query.
     *
     * @return array
     */
    public function getEntities()
    {
        return $this->entities;
    }

    /**
     * Returns select sources.
     *
     * @return array
     */
    public function getSelectSources()
    {
        return $this->selectSources;
    }

    /**
     * Shortcut to calling $map = new QueryMetaMap($qb); $map->parse();
     *
     * @param QueryBuilder $queryBuilder
     * @return QueryMetaMap
     */
    public static function create($queryBuilder)
    {
        $map = new QueryMetaMap;
        $map->parse($queryBuilder);
        return $map;
    }
    
}