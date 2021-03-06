<?php

namespace CTLib\Component\DataAccess;

use CTLib\Util\Arr;

/**
 * Facilitates retrieving and processing data
 * results into structured data.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class DataProvider
{
    /**
     * @var array
     */
    protected $fields;


    /**
     * DataProvider constructor.
     */
    public function __construct()
    {
        $this->fields        = [];
        $this->onRecordAdded = null;
        $this->onBeforeRecordAdded = null;
    }

    /**
     * @param $method
     *
     * @throws \Exception
     */
    public function setOnRecordAdded($method)
    {
        $this->onRecordAdded = $method;
    }

    /**
     * @param $method
     *
     * @throws \Exception
     */
    public function setOnBeforeRecordAdded($method)
    {
        $this->onBeforeRecordAdded = $method;
    }

    /**
     * Method to facilitate field management between
     * the data retrieval and the final output result.
     *
     * @param $field string|callable
     * $param $alias string
     *
     * @return DataProvider
     *
     * @throws \Exception
     */
    public function addField($field, $alias=null)
    {
        if (is_callable($field) && !$alias) {
            throw new \InvalidArgumentException('alias is required for callable');
        }

        if (!$alias) {
            $alias = $field;
        }

        $this->fields[$alias] = $field;

        return $this;
    }

    /**
     * @param array $fields
     *
     * @return DataProvider
     */
    public function addFields(array $fields)
    {
        foreach ($fields as $field) {
            $alias = null;

            if (is_array($field)) {
                list($field, $alias) = $field;
            }

            $this->addField($field, $alias);
        }
    }

    /**
     * Get transformed data retrieved from data source.
     * Output will be whatever is generated by the
     * data output class.
     *
     * @param DataAccessInterface $dataAccess
     * @param DataOutputInterface $output
     *
     * @return mixed
     */
    public function getResult(
        DataAccessInterface $dataAccess,
        DataOutputInterface $output
    ) {
        // Send the aliases to output
        $fields = array_keys($this->fields);
        $output->start($fields);

        $data = $dataAccess->getData();
        $count = 0;

        if (isset($data['count'])) {
            $count = $data['count'];
        }
        if (isset($data['data'])) {
            $data = $data['data'];
        }

        foreach ($data as $rawRecord) {
            $record  = [];
            $context = [];

            foreach ($this->fields as $alias => $field) {
                if (is_string($field)) {
                    // Retrieve from $rawRecord using array path
                    $value = Arr::findByKeyChain($rawRecord, $field);
                } else {
                    // Hand off to callback to get value
                    $value = call_user_func_array($field, [$rawRecord, &$context]);
                }
                $record[$alias] = $value;
            }

            if ($this->onBeforeRecordAdded) {
                call_user_func_array($this->onBeforeRecordAdded, [&$record]);
            }

            $output->addRecord($record);

            if ($this->onRecordAdded) {
                call_user_func($this->onRecordAdded, $record, $output);
            }
        }

        return $output->end($count);
    }
}
