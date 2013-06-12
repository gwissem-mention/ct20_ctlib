<?php
namespace CTLib\Repository;

use Doctrine\ORM\NoResultException,
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
     * @return mixed        Either found entity instance or null.
     * @throws Exception    If $id is defined incorrectly for primary key.
     */
    public function findEffective($id, $effectiveTime=null)
    {
        // Ensure that passed $id matches logical (non-effective) ID fields
        // for this entity.
        $idFieldNames = $this->getEntityManager()
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
        $qb = $this->createQueryBuilder('e');
        foreach ($criteria AS $idFieldName => $value) {
            $qb->andWhere("e.{$idFieldName} = :{$idFieldName}")
                ->setParameter($idFieldName, $value);
        }
        $qb->andWhere("e.effectiveTime <= {$effectiveTime}")
            ->addOrderBy('e.effectiveTime', 'DESC')
            ->setMaxResults(1);
        $result = $qb->getQuery()
                    ->getResult();
        return $result ? current($result) : null;
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
     * @throws NoResultException
     */
    public function mustFindEffective($id, $effectiveTime=null)
    {
        $result = $this->findEffective($id, $effectiveTime);
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
    public function findByEffective(array $criteria, $effectiveTime=null, array $orderBy=null, $limit=null, $offset=null)
    {
        $qb = $this->createFilteredEffectiveQueryBuilder(
                $criteria,
                $effectiveTime
        );

        if ($orderBy) {
            foreach ($orderBy as $fieldName => $direction) {
                $qb->orderBy($fieldName, $direction);
            }
        }
        if ($limit) {
            $qb->setMaxResults($limit);
        }
        if ($offset) {
            $qb->setFirstResult($offset);
        }
        return $qb->getQuery()
                    ->getResult();
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
    public function mustFindByEffective(array $criteria, $effectiveTime=null, array $orderBy=null, $limit=null, $offset=null)
    {
        $result = $this->findByEffective(
            $criteria,
            $effectiveTime,
            $orderBy,
            $limit,
            $offset
        );
        if (! $result) { throw new NoResultException; }
        return $result;
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
        return (int) $this->createFilteredEffectiveQueryBuilder(
                        $criteria,
                        $effectiveTime)
                    ->select(array('count(e)'))
                    ->getQuery()->getSingleScalarResult();
    }

    /**
     * Adds wrappers to handle:
     *
     *  - findBy{FieldName}Effective($value, $effectiveTime=null)
     *  - mustFindBy{FieldName}Effective($value, $effectiveTime=null)
     */
    public function __call($methodName, $args)
    {
        $pattern = '/^(must)?(?:f|F)indBy([A-Z][A-Za-z]+)Effective$/';

        if (preg_match($pattern, $methodName, $matches)) {
            $mustFind       = $matches[1] != '';
            $fieldName      = lcfirst($matches[2]);
            $value          = Arr::mustGet(0, $args);
            $effectiveTime  = Arr::get(1, $args);
            
            $result = $this->findByEffective(
                array($fieldName => $value),
                $effectiveTime
            );
            if ($mustFind && ! $result) { throw new NoResultException; }
            return $result;
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
    protected function createFilteredEffectiveQueryBuilder(array $criteria, $effectiveTime=null)
    {
        return $this->createFilteredQueryBuilder($criteria)
                    ->andEffectiveWhere('e', $effectiveTime);
    }
    

}
