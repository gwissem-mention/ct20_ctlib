<?php

namespace CTLib\Component\DataAccess;

/**
 * Interface used to implement a data provider
 * that will format the data results retrieved
 * from a data source.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
interface DataOutputInterface
{
    /**
     * @param array $input
     */
    public function start(array $input);

    /**
     * @param array $record
     */
    public function addRecord(array $record);

    /**
     * @param array $input
     */
    public function end(array $input);
}
