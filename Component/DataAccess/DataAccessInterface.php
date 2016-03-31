<?php

namespace CTLib\Component\DataAccess;

/**
 * Interface used to implement a data provider
 * that will retrieve data from a data source.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
interface DataAccessInterface
{
    /**
     */
    public function getData();

    /**
     * @param string $field
     * @param string $alias
     */
    public function addField($field, $alias=null);

    /**
     * @param string $field
     * @param mixed  $value
     * @param string $operator
     */
    public function addFilter($field, $value, $operator);

    /**
     * @param string $field
     * @param string $order
     */
    public function addSort($field, $order);

    /**
     * @param integer $maxResults
     */
    public function setMaxResults($maxResults);

    /**
     * @param integer $offset
     */
    public function setOffset($offset);
}
