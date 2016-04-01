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
     * Constants for sort order
     */
    const SORT_ASC  = 'ASC';
    const SORT_DESC = 'DESC';

    /**
     */
    public function getData();

    /**
     * @param string $field
     */
    public function addField($field);

    /**
     * @param string|callable   $field
     * @param mixed|null        $value
     * @param string|null       $operator
     */
    public function addFilter($field, $value=null, $operator='eq');

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

    /**
     * @return array
     */
    public function getFields();
}
