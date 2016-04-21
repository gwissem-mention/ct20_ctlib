<?php

namespace CTLib\Component\DataAccess;

/**
 * Facilitates retrieving and processing nosql
 * results into csv output.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class CsvDataOutput implements DataOutputInterface
{
    /**
     * @var array
     */
    protected $columns = [];

    /**
     * store csv file handler
     * @var mixed
     */
    protected $fileHandle;

    /**
     * stores csv temporary file name
     * @var string
     */
    protected $fileName;

    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * @param Array $columns
     */
    public function setColumns($columns = null)
    {
        $this->columns = $columns;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $fields
     *
     */
    public function start(array $fields)
    {
        $this->fileHandle = fopen($this->fileName, "w");

        if (!$this->fileHandle) {
            throw new \Exception("CSV file creation failed");
        }

        if ($this->columns) {
            // Write csv header row with customized columns
            fputcsv($this->fileHandle, $this->columns);
        } else {
            // Write csv header row as default
            fputcsv($this->fileHandle, $fields);
        }
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
        fputcsv($this->fileHandle, $record);
    }

    /**
     * {@inheritdoc}
     *
     * @param array $fields
     *
     */
    public function end()
    {
        rewind($this->fileHandle);
        $content = stream_get_contents($this->fileHandle);
        fclose($this->fileHandle);

        return $content;
    }
}
