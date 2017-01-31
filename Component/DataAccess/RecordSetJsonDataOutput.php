<?php
/**
 * Facilitates processing document results and returning an
 * array of fields results to the UI aliases
 *
 * @author Sean Hunter <shunter@celltrak.com>
 */

namespace CTLib\Component\DataAccess;

use CTLib\Util\Arr;

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
        // convert to enumerted list to work with recorset set Model
        $this->records[] = array_values($record);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $count
     *
     * @return array json_encoded
     */
    public function end($count = 0)
    {
        // return in format of Data / Model / Count
        return json_encode([
            'data'  => $this->records,
            'model' => $this->fields,
            'total' => $count
        ]);
    }
}
