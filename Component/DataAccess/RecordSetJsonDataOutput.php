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
    public function end()
    {
        return json_encode( array(  'data' => $this->records,
                                    'model' => $this->getModelAliases()));

      //  return json_encode($this->records);
    }
}