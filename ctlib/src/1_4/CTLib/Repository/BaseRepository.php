<?php
namespace CTLib\Repository;

use Doctrine\DBAL\LockMode,
    Doctrine\ORM\NoResultException,
    Doctrine\ORM\NonUniqueResultException,
    CTLib\Component\Doctrine\ORM\DetachedEntityIterator,
    CTLib\Util\Arr;

class BaseRepository extends \Doctrine\ORM\EntityRepository
{

    /**
     * Override standard find because Doctrine's version will dangerously handle
     * case when multiple records found by simply returning first record.
     *
     * Also, standard find method will return unpredictable results based on
     * state of EntityManager.  Sometimes will return NULL if record not found
     * and other times will return entity proxy instance (which evals to TRUE).
     *
     * @param array $id             Pass as array($idFieldName => $value).
     *                              If only 1 ID field, you can just send $value.
     * @return Entity 
     */
    public function find($id)
    {
        $idFieldNames = $this->getEntityManager()
                            ->getEntityMetaHelper()
                            ->getIdentifierFieldNames($this->entityName());
        $criteria = $this->coalesceKeyValues($idFieldNames, $id);
        return $this->findUniqueBy($criteria);
    }

    public function _find($id)
    {
        $idFieldNames = $this->getEntityManager()
                            ->getEntityMetaHelper()
                            ->getIdentifierFieldNames($this->entityName());
        $criteria = $this->coalesceKeyValues($idFieldNames, $id);
        return $this->_findUniqueBy($criteria);
    }

    public function _findUniqueBy($criteria)
    {
        $results = $this->_findBy($criteria, null, 2);

        switch (count($results)) {
            case 0:
                return null;
            case 1:
                return $results[0];
            default:
                throw new NonUniqueResultException;
        }
    }

    public function _findBy(array $criteria, array $orderBy=null,
        $limit=null, $offset=null)
    {
        $fieldNames = $this
                        ->getEntityManager()
                        ->getEntityMetaHelper()
                        ->getFieldNames($this->entityName());

        $qbr = $this->createFilteredQueryBuilder($criteria);

        $select = array_map(function($f) { return "e.{$f}"; }, $fieldNames);
        $qbr->select(join(', ', $select));

        if ($orderBy) {
            foreach ($orderBy as $fieldName => $dir) {
                $qbr->addOrderBy("e.{$fieldName}", $dir);
            }    
        }

        if ($limit) {
            $qbr->setMaxResults($limit);
        }

        if ($offset) {
            $qbr->setFirstResult($offset);
        }

        $className = $this
                        ->getEntityManager()
                        ->getEntityMetaHelper()
                        ->getClassName($this->entityName());

        $results = $qbr->getQuery()->getResult();
        return new DetachedEntityIterator($results, $className);
    }

    /**
     * Functions like find but throws exception if no result found.
     *
     * @param array $id             Pass as array($idFieldName => $value).
     *                              If only 1 ID field, you can just send $value.
     * @return Entity
     * @throws NoResultException
     */
    public function mustFind($id)
    {
        $result = $this->find($id);
        if (! $result) { throw new NoResultException; }
        return $result;
    }

    /**
     * Functions like standard findAll but throws exception if no results found.
     *
     * @return ArrayCollection
     * @throws NoResultException
     */
    public function mustFindAll()
    {
        $result = $this->findAll();
        if (! $result) { throw new NoResultException; }
        return $result;
    }

    /**
     * Functions like standard findBy but throws exception if no results found.
     *
     * @param array $criteria       array($entityFieldName => $value)
     * @param array $orderBy        array($entityFieldName => $sortDirection)
     * @param int $limit            Max results to return.
     * @param int $offset           First result position to return.
     *
     * @return ArrayCollection
     * @throws NoResultException
     */
    public function mustFindBy(array $criteria, array $orderBy=null, $limit=null, $offset=null)
    {
        $result = $this->findBy($criteria, $orderBy, $limit, $offset);
        if (! $result) { throw new NoResultException; }
        return $result;
    }

    /**
     * Functions like standard findOneBy but throws exception if no result found.
     *
     * @param array $criteria       array($entityFieldName => $value)
     *
     * @return Entity
     * @throws NoResultException
     */
    public function mustFindOneBy(array $criteria)
    {
        $result = $this->findOneBy($criteria);
        if (! $result) { throw new NoResultException; }
        return $result;
    }

    /**
     * Looks for at most 1 resulting record. Throws exception if more than 1 found.
     *
     * @param array $criteria       array($entityFieldName => $value)
     *
     * @return Entity
     * @throws NonUniqueResultException
     */
    public function findUniqueBy(array $criteria)
    {
        // Use Doctrine's standard findBy method so we can catch if multiple
        // records found.
        $result = $this->findBy($criteria, null, 2);

        switch (count($result)) {
            case 0:
                return null;
            case 1:
                return $result[0];
            default:
                throw new NonUniqueResultException;
        }
    }

    /**
     * Functions like findUniqueBy but throws exception if no result found.
     *
     * @param array $criteria       array($entityFieldName => $value)
     *
     * @return Entity
     * @throws NoResultException
     */
    public function mustFindUniqueBy(array $criteria)
    {
        $result = $this->findUniqueBy($criteria);
        if (! $result) { throw new NoResultException; }
        return $result;
    }

    /**
     * Checks to see if any record matches $criteria.
     *
     * @param array $criteria       array($entityFieldName => $value)
     * @return boolean
     */
    public function exists(array $criteria)
    {   
        return $this->count($criteria) > 0;
    }

    /**
     * Throws exception if no record matches $criteria.
     *
     * @param array $criteria       array($entityFieldName => $value)
     *
     * @return void
     * @throws NoResultException
     */
    public function mustExist(array $criteria)
    {
        if (! $this->exists($criteria)) { throw new NoResultException; }
        return;
    }

    /**
     * Returns number of records that match $criteria using SQL COUNT().
     *
     * @param array $criteria   array($entityFieldName => $value)
     * @return int
     */
    public function count(array $criteria)
    {
        return (int) $this->createFilteredQueryBuilder($criteria)
                    ->select(array('count(e)'))
                    ->getQuery()->getSingleScalarResult();
    }

    /**
     * Add wrappers to handle:
     *
     *  - mustFindOneBy{FieldName}($value)
     *  - mustFindBy{FieldName}($value)
     *  - findUniqueBy{FieldName}($value)
     *  - mustFindUniqueBy{FieldName}($value)
     */
    public function __call($methodName, $args)
    {
        if (strpos($methodName, 'mustFindOneBy') === 0) {
            // Handle mustFindOneBy{FieldName}.
            $fieldName  = lcfirst(substr($methodName, 13));
            $value      = Arr::mustGet(0, $args);
            return $this->mustFindOneBy(array($fieldName => $value));

        } elseif (strpos($methodName, 'mustFindBy') === 0) {
            // Handle mustFindBy{FieldName}.
            $fieldName  = lcfirst(substr($methodName, 10));
            $value      = Arr::mustGet(0, $args);
            return $this->mustFindBy(array($fieldName => $value));

        } elseif (strpos($methodName, 'findUniqueBy') === 0) {
            // Handle findUniqueBy{FieldName}.
            $fieldName  = lcfirst(substr($methodName, 12));
            $value      = Arr::mustGet(0, $args);
            return $this->findUniqueBy(array($fieldName => $value));

        } elseif (strpos($methodName, 'mustFindUniqueBy') === 0) {
            // Handle mustFindUniqueBy{FieldName}.
            $fieldName  = lcfirst(substr($methodName, 16));
            $value      = Arr::mustGet(0, $args);
            return $this->mustFindUniqueBy(array($fieldName => $value));
            
        } else {
            return parent::__call($methodName, $args);
        }
    }

    /**
     * Creates DataProviderQueryBuilder for this Entity.
     *
     * @param string $alias
     * @return DataProviderQueryBuilder
     */
    public function createDataProviderQueryBuilder($alias)
    {
        return $this->_em->createDataProviderQueryBuilder()
            ->select($alias)
            ->from($this->_entityName, $alias);
    }

    /**
     * Creates QueryBuilder filtered with passed $criteria.
     *
     * @param array $criteria   array($entityFieldName => $value)
     * @return QueryBuilder
     */
    protected function createFilteredQueryBuilder(array $criteria)
    {
        $qbr = $this->createQueryBuilder('e');
        foreach ($criteria as $fieldName => $value) {
            $operator = is_array($value) ? 'in' : 'eq';
            $expr = $qbr->expr()->{$operator}("e.{$fieldName}", ":{$fieldName}");
            $qbr->andWhere($expr)
                ->setParameter($fieldName, $value);
        }
        return $qbr;
    }

    /**
     * Brings key fields/attributes together with their respective values.
     * 
     * @param array $keyFields  Enumerated array of key fields.
     * @param mixed $values     Either associative array of key fields
     *                          => values or single value if only 1 key field.
     * @return array            Associative array of key field => value.
     */
    protected function coalesceKeyValues($keyFields, $values)
    {
        if (! is_array($values)) {
            if (count($keyFields) > 1) {
                throw new \Exception("Must pass value for each key field as [field=>value]");
            }
            return array(current($keyFields) => $values);
        }

        if (! Arr::match($keyFields, array_keys($values))) {
            throw new \Exception("\$keyFields and fields in \$values do not match.");
        }
        return $values;
    }

    /**
     * Shortcut to built-in getEntityManager method.
     * @return EntityManager
     */
    protected function em()
    {
        return $this->getEntityManager();
    }

    /**
     * Shortcut to getting expression builder
     *
     * @return Query\Expr return expression builder
     *
     */
    protected function expr()
    {
        return $this->getEntityManager()->getExpressionBuilder();
    }

    /**
     * Shortcut to get entity name from class metadata.
     *
     * @return string
     */
    protected function entityName()
    {
        return $this->getClassMetadata()->name;
    }
    
}