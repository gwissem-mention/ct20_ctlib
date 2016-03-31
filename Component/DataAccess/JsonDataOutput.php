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
class JsonDataOutput implements DataOutputInterface
{
    /**
     * @var array
     */
    protected $records = [];


    /**
     * {@inheritdoc}
     *
     * @param DataAccessInterface $input
     *
     */
    public function start(DataAccessInterface $input)
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
     * @param DataAccessInterface $input
     *
     * @return JsonResponse
     */
    public function end(DataAccessInterface $input)
    {

        return new JsonResponse($this->records);
    }
}
