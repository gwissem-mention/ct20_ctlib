<?php
/**
 * Facilitates processing document results and returning an
 * array of fields results to the UI aliases
 *
 * @author Sean Hunter <shunter@celltrak.com>
 */

namespace CTLib\Component\DataAccess;

use CTLib\Util\Arr;
use CTLib\Component\HttpFoundation\JsonResponse;

class RecordSetJsonDataOutput implements DataOutputInterface
{
    /**
     * @var array
     */
    protected $records = [];

    /**
     * @var array
     */
    protected $fields = [];


    /**
     * {@inheritdoc}
     *
     * @param array $fields
     *
     */
    public function start(array $fields)
    {
        $this->records = [];
        $this->fields = $fields;
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
    public function end()
    {
        // convert to enumerted list to work with recorset set Model
        $data = [];
        foreach($this->records as $record){
            $enumData = [];
            foreach($record as $item){
                $enumData[] = $item;
            }
            $data[] = $enumData;
        }

        return json_encode( array(  'data' => $data,
                                    'model' => $this->fields));
    }
}