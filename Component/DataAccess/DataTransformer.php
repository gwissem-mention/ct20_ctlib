<?php

namespace CTLib\Component\DataAccess;

/**
 * Interface used to implement a data provider
 * that will transform data from one format to another.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
interface DataTransformer
{
    /**
     * @param array $record
     */
    public function onBeforeRecordAdded(array $record);

    /**
     * @param array $record
     */
    public function onAfterRecordAdded(array $record);
}
