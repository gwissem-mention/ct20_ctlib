<?php
namespace CTLib\Repository;

use Doctrine\ORM\NoResultException,
    Doctrine\ORM\NonUniqueResultException,
    CTLib\Component\Doctrine\ORM\DetachedEntityIterator,
    CTLib\Util\Arr;


class EffectiveRepository extends BaseRepository
{
    
    /**
     * Retrieve effective record by ID (a.k.a. primary key).
     *
     * @param array $id             Pass as array($idFieldName => $value).
     *                              If only 1 ID field, you can just send $value.
     * @param int $effectiveTime    If null, will use current time.
     *
     * @return Entity|null
     *
     * @throws Exception    If $id is defined incorrectly for primary key.
     * @throws NonUniqueResultException
     */
    public function findEffective($id, $effectiveTime=null)
    {
        $results = $this
                    ->createLogicalIdEffectiveQueryBuilder($id, $effectiveTime)
                    ->getQuery()
                    ->getResult();

        switch (count($results)) {
            case 0:
                return null;
            case 1:
                return current($results);
            default:
                throw new NonUniqueResultException;
        }
    }

    /**
     * Retrieve effective record by ID (a.k.a. primary key).
     * Unlike findEffective, will throw exception if no record found.
     *
     * @param array $id     Pass as array($idFieldName => $value).
     *                      If only 1 ID attribute, you can just send the
     *                      filterValue (no array).
     * @param int $effectiveTime    If null, will use current time.
     *
     * @return Entity       Found entity instance.
     *
     * @throws Exception    If $id is defined incorrectly for primary key.
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function mustFindEffective($id, $effectiveTime=null)
    {
        $result = $this->findEffective($id, $effectiveTime);
        if (! $result) { throw new NoResultException; }
        return $result;
    }

    /**
     * Functions like #findEffective except that entity returned is not managed
     * by the EntityManager.
     *
     * @param array $id             Pass as array($idFieldName => $value).
     *                              If only 1 ID field, you can just send $value.
     * @param int $effectiveTime    If null, will use current time.
     *
     * @return Entity|null
     *
     * @throws Exception    If $id is defined incorrectly for primary key.
     * @throws NonUniqueResultException
     */
    public function _findEffective($id, $effectiveTime=null)
    {
        $results = $this
                    ->createLogicalIdEffectiveQueryBuilder($id, $effectiveTime)
                    ->select($this->getSelectFieldsDql())
                    ->getQuery()
                    ->getResult();

        switch (count($results)) {
            case 0:
                return null;
            case 1:
                $entityMetadata = $this
                                    ->getEntityManager()
                                    ->getEntityMetaHelper()
                                    ->getMetadata($this->entityName());
                $results = new DetachedEntityIterator($results, $entityMetadata);
                return $results[0];
            default:
                throw new NonUniqueResultException;
        }
    }

    /**
     * Functions like #mustFindEffective except that entity returned is not
     * managed by the EntityManager.
     *
     * @param array $id     Pass as array($idFieldName => $value).
     *                      If only 1 ID attribute, you can just send the
     *                      filterValue (no array).
     * @param int $effectiveTime    If null, will use current time.
     *
     * @return Entity       Found entity instance.
     *
     * @throws Exception    If $id is defined incorrectly for primary key.
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function _mustFindEffective($id, $effectiveTime=null)
    {
        $result = $this->_findEffective($id, $effectiveTime);
        if (! $result) { throw new NoResultException; }
        return $result;
    }
    
    /**
     * Retrieves all effective records.
     *
     * @param int $effectiveTime    If null, will use current time.
     * @return ArrayCollection
     */
    public function findAllEffective($effectiveTime=null)
    {
        return $this->findByEffective(array(), $effectiveTime);
    }

    /**
     * Retrieves all effective records.
     * Unlike findAllEffective, will throw exception if no records found.
     *
     * @param int $effectiveTime    If null, will use current time.
     * @return ArrayCollection
     * @throws NoResultException
     */
    public function mustFindAllEffective($effectiveTime=null)
    {
        $result = $this->findAllEffective($effectiveTime);
        if (! $result) { throw new NoResultException; }
        return $result;
    }

    /**
     * Functions like #findAllEffective except that entities returned are not
     * managed by the EntityManager.
     *
     * @param int $effectiveTime    If null, will use current time.
     * @return DetachedEntityIterator
     */
    public function _findAllEffective($effectiveTime=null)
    {
        return $this->_findByEffective(array(), $effectiveTime);
    }

    /**
     * Functions like #mustFindAllEffective except that entities returned are
     * not managed by the EntityManager.
     *
     * @param int $effectiveTime    If null, will use current time.
     *
     * @return DetachedEntityIterator
     * @throws NoResultException
     */
    public function _mustFindAllEffective($effectiveTime=null)
    {
        $result = $this->_findAllEffective($effectiveTime);
        if (! $result) { throw new NoResultException; }
        return $result;
    }

    /**
     * Find multiple effective records filtered by $criteria.
     *
     * @param array $criteria       array($entityFieldName => $value)
     * @param int $effectiveTime    If null, will use current time.
     * @param array $orderBy        array($entityFieldName => $sortDirection)
     * @param int $limit            Max results to return.
     * @param int $offset           First result position to return.
     *
     * @return ArrayCollection
     */
    public function findByEffective(
                        array $criteria,
                        $effectiveTime=null,
                        array $orderBy=null,
                        $limit=null,
                        $offset=null)
    {
        $qbr = $this
                ->createFilteredEffectiveQueryBuilder($criteria, $effectiveTime);

        if ($orderBy) {
            foreach ($orderBy as $fieldName => $direction) {
                $qbr->orderBy("e.{$fieldName}", $direction);
            }
        }

        if ($limit) {
            $qbr->setMaxResults($limit);
        }

        if ($offset) {
            $qbr->setFirstResult($offset);
        }

        return $qbr->getQuery()->getResult();
    }

    /**
     * Find multiple effective records filtered by $criteria.
     * Unlike findByEffective, will throw exception if no records found.
     *
     * @param array $criteria       array($entityFieldName => $value)
     * @param int $effectiveTime    If null, will use current time.
     * @param array $orderBy        array($entityFieldName => $sortDirection)
     * @param int $limit            Max results to return.
     * @param int $offset           First result position to return.
     *
     * @return ArrayCollection
     * @throws NoResultException
     */
    public function mustFindByEffective(
                        array $criteria,
                        $effectiveTime=null,
                        array $orderBy=null,
                        $limit=null,
                        $offset=null)
    {
        $results = $this
                    ->findByEffective(
                        $criteria,
                        $effectiveTime,
                        $orderBy,
                        $limit,
                        $offset);
        if (! $results) { throw new NoResultException; }
        return $results;
    }

    /**
     * Functions like #findByEffective except that entities returned are not
     * managed by the EntityManager.
     *
     * @param array $criteria       array($entityFieldName => $value)
     * @param int $effectiveTime    If null, will use current time.
     * @param array $orderBy        array($entityFieldName => $sortDirection)
     * @param int $limit            Max results to return.
     * @param int $offset           First result position to return.
     *
     * @return DetachedEntityIterator
     */
    public function _findByEffective(
                        array $criteria,
                        $effectiveTime=null,
                        array $orderBy=null,
                        $limit=null,
                        $offset=null)
    {
        $qbr = $this
                ->createFilteredEffectiveQueryBuilder($criteria, $effectiveTime)
                ->select($this->getSelectFieldsDql());

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

        $results = $qbr->getQuery()->getResult();

        if (! $results) { return array(); }

        $entityMetadata = $this
                            ->getEntityManager()
                            ->getEntityMetaHelper()
                            ->getMetadata($this->entityName());
        return new DetachedEntityIterator($results, $entityMetadata);
    }

    /**
     * Functions like #mustFindByEffective except that entities returned are not
     * managed by the EntityManager.
     *
     * @param array $criteria       array($entityFieldName => $value)
     * @param int $effectiveTime    If null, will use current time.
     * @param array $orderBy        array($entityFieldName => $sortDirection)
     * @param int $limit            Max results to return.
     * @param int $offset           First result position to return.
     *
     * @return DetachedEntityIterator
     * @throws NoResultException
     */
    public function _mustFindByEffective(
                        array $criteria,
                        $effectiveTime=null,
                        array $orderBy=null,
                        $limit=null,
                        $offset=null)
    {
        $results = $this
                    ->_findByEffective(
                        $criteria,
                        $effectiveTime,
                        $orderBy,
                        $limit,
                        $offset);
        if (! $results) { throw new NoResultException; }
        return $results;
    }

    /**
     * Checks to see if any effective record matches $criteria.
     *
     * @param array $criteria       array($entityFieldName => $value)
     * @param int $effectiveTime    If null, will use current time.
     *
     * @return boolean
     */
    public function existsEffective(array $criteria, $effectiveTime=null)
    {
        return $this->countEffective($criteria, $effectiveTime) > 0;
    }

    /**
     * Throws exception if no effective record matches $criteria.
     *
     * @param array $criteria       array($entityFieldName => $value)
     * @param int $effectiveTime    If null, will use current time.
     *
     * @return void
     * @throws NoResultException
     */    
    public function mustExistEffective(array $criteria, $effectiveTime=null)
    {
        if (! $this->existsEffective($criteria, $effectiveTime)) {
            throw new NoResultException;
        }
        return;
    }

    /**
     * Returns number of effective records that match $criteria using
     * SQL COUNT().
     *
     * @param array $criteria       array($entityFieldName => $value)
     * @param int $effectiveTime    If null, will use current time.
     *
     * @return int
     */
    public function countEffective(array $criteria, $effectiveTime=null)
    {
        return (int) $this
                        ->createFilteredEffectiveQueryBuilder(
                            $criteria,
                            $effectiveTime)
                        ->select(array('count(e)'))
                        ->getQuery()
                        ->getSingleScalarResult();
    }

    /**
     * Adds wrappers to handle:
     *
     *  - findBy{FieldName}Effective($value, $effectiveTime=null)
     *  - mustFindBy{FieldName}Effective($value, $effectiveTime=null)
     *  - _findBy{FieldName}Effective($value, $effectiveTime=null)
     *  - _mustFindBy{FieldName}Effective($value, $effectiveTime=null)
     */
    public function __call($methodName, $args)
    {
        $pattern = '/^(_)?(must)?(?:f|F)indBy([A-Z][A-Za-z]+)Effective$/';

        if (preg_match($pattern, $methodName, $matches)) {
            $useDetached    = $matches[1] != '';
            $mustFind       = $matches[2] != '';
            $fieldName      = lcfirst($matches[3]);
            $value          = Arr::mustGet(0, $args);
            $criteria       = array($fieldName => $value);
            $effectiveTime  = Arr::get(1, $args);
            
            if (! $useDetached && ! $mustFind) {
                $findMethod = 'findByEffective';
            } elseif (! $useDetached && $mustFind) {
                $findMethod = 'mustFindByEffective';
            } elseif ($useDetached && ! $mustFind) {
                $findMethod = '_findByEffective';
            } else {
                $findMethod = '_mustFindByEffective';
            }

            return $this->{$findMethod}($criteria, $effectiveTime);

        } else {
            parent::__call($methodName, $args);
        }
    }

    /**
     * Creates QueryBuilder filtered with passed $criteria and $effectiveTime.
     *
     * @param array $criteria       array($entityFieldName => $value)
     * @param int $effectiveTime    If null, will use current time.
     *
     * @return QueryBuilder
     */
    protected function createFilteredEffectiveQueryBuilder(
                        array $criteria,
                        $effectiveTime=null)
    {
        return $this
                ->createFilteredQueryBuilder($criteria)
                ->andEffectiveWhere('e', $effectiveTime);
    }

    /**
     * Creates QueryBuilder filtered to look for one effective record based on
     * entity's logical primary key.
     *
     * @param array $id             Pass as array($idFieldName => $value).
     *                              If only 1 ID field, you can just send $value.
     * @param int $effectiveTime    If null, will use current time.
     *
     * @return QueryBuilder
     * @throws Exception    If $id is defined incorrectly for primary key. 
     */
    protected function createLogicalIdEffectiveQueryBuilder(
                        $id,
                        $effectiveTime=null)
    {
        // Ensure that passed $id matches logical (non-effective) ID fields
        // for this entity.
        $idFieldNames = $this
                            ->getEntityManager()
                            ->getEntityMetaHelper()
                            ->getLogicalIdentifierFieldNames($this->entityName());
        $criteria = $this->coalesceKeyValues($idFieldNames, $id);

        if ($effectiveTime) {
            if (! is_int($effectiveTime) || $effectiveTime < 0) {
                throw new \Exception('$effectiveTime must be an unsigned integer');
            }
        } else {
            $effectiveTime = time();
        }

        // Since we're looking for exactly 1 record based on logical primary
        // key, we can use the efficient SELECT ... ORDER BY effective_time DESC
        // query rather than the typical effective subquery.
        // Ensure that ID critiera doesn't support array values b/c we must be
        // looking for only 1 matching logical primary key.
        return $this
                ->createFilteredQueryBuilder($criteria)
                ->andWhere("e.effectiveTime <= :effectiveTime")
                ->setParameter('effectiveTime', $effectiveTime)
                ->orderBy('e.effectiveTime', 'DESC')
                ->setMaxResults(1);
    }

}
