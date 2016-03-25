<?php

namespace CTLib\Component\DataProvider;

/**
 * Interface used to implement a data provider
 * that will retrieve data from a data source.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
interface DataAccessInterface
{
    /**
     * @param Request $request
     */
    public function getData($request);

    /**
     * @param string $field
     * @param string $alias
     */
    public function addField($field, $alias=null);

    /**
     * @param string $field
     * @param mixed  $filter
     */
    public function addFilter($field, $filter);

    /**
     * @param string $field
     * @param string $order
     */
    public function addSort($field, $order);

    /**
     * @param integer $maxResults
     */
    public function setMaxResults($maxResults);

}
