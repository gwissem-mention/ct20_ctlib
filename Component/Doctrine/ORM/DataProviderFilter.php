<?php
namespace CTLib\Component\Doctrine\ORM;

/**
 * Abstract Filter that is applied to dql or query builder 
 */
abstract class DataProviderFilter
{
    abstract protected function filterHandler($queryBuilder, $filters, $entityAliases);
    
    public function applyFilterHandler($queryBuilder, $filterValue)
    {
        $entityManager = $queryBuilder->getEntityManager();
        $entityAliases = array_reduce(
            $entityManager->getQueryMetaMap($queryBuilder)->getEntities(),
            function(&$result , $meta) {
                $result[$meta->name] = $meta->alias;
                $shortName = str_replace('\Entity\\', ':', $meta->name);
                $result[$shortName] = $meta->alias;
                return $result;
            },
            array()
        );
    
        $this->filterHandler($queryBuilder, $filterValue, $entityAliases);
    }
}