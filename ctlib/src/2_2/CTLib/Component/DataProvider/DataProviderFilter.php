<?php
namespace CTLib\Component\DataProvider;

/**
 * API used by DataProvider filter classes.
 *
 * @author Shuang Liu <sliu@celltrak.com>
 * @author Mike Turoff <mturoff@celltrak.com> 
 */
interface DataProviderFilter
{
    
    /**
     * Applies filter.
     *
     * @param QueryBuilder $queryBuilder
     * @param mixed $value
     *
     * @return void
     */
    public function apply($queryBuilder, $value);

}