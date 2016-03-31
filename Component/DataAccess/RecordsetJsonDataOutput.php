<?php

namespace CTLib\Component\DataAccess;

use CTLib\Util\Arr;
use CTLib\Component\HttpFoundation\JsonResponse;

/**
 * Facilitates retrieving and processing nosql
 * results into structured data.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class RecordsetJsonDataOutput implements DataOutputInterface
{
    /**
     * @var array
     */
    protected $records = [];


    /**
     * {@inheritdoc}
     *
     * @param array $fields
     *
     */
    public function start(array $fields)
    {
        $this->records = [];
    }

    /**
     * {@inheritdoc}
     *
     * Perform the necessary processing to create a flat
     * Record of field values.
     *
     * @param array $record   Document data retrieved from API
     */
    public function addRecord(array $record)
    {


        $this->records[] = $record;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $fields
     *
     * @return string
     */
    public function end(array $fields)
    {

        return json_encode($this->records);
    }
}
