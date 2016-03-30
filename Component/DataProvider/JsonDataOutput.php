<?php

namespace CTLib\Component\DataProvider;

use CTLib\Util\Arr;
use CTLib\Component\HttpFoundation\JsonResponse;

/**
 * Facilitates retrieving and processing nosql
 * results into structured data.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class JsonDataOutput implements DataOutputInterface
{
    /**
     * @var array
     */
    protected $records = [];


    /**
     * {@inheritdoc}
     *
     * @param DataInputInterface $input
     *
     */
    public function start(DataInputInterface $input)
    {

    }

    /**
     * {@inheritdoc}
     *
     * Perform the necessary processing to create a flat
     * Record of field values.
     *
     * @param array $record   Document data retrieved from API
     *
     * @return array
     */
    public function addRecord(array $record)
    {
        $processedRecord = [];


        return $processedRecord;
    }

    /**
     * {@inheritdoc}
     *
     * @param DataInputInterface $input
     *
     * @return array
     */
    public function end(DataInputInterface $input)
    {

        return $this->records;
    }
}
