<?php

namespace CTLib\Component\DataProvider;

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
     * @param DataInputInterface $input
     */
    public function start(DataInputInterface $input);

    /**
     * @param array $record
     */
    public function addRecord(array $record);

    /**
     * @param DataInputInterface $input
     */
    public function end(DataInputInterface $input);
}
