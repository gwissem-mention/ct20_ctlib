<?php

namespace CTLib\Component\DataAccess;

use CTLib\Component\HttpFoundation\CsvFileResponse;

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
    protected $records = [];

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

    /**
     * @var Request
     */
    protected $request;

    /**
     * @param Request $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
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
        $this->records = [];

        $this->fileName   = $this->createTempFileName("rst");
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

        $this->records[] = $record;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $fields
     *
     */
    public function end()
    {
        fclose($this->fileHandle);

        return new CsvFileResponse(
            $this->request,
            $this->fileName,
            "celltrak" . date("YmdHis") . ".csv"
        );
    }

    /**
     * create Temp File
     *
     * @return string the name of created temporary file
     *
     */
    protected function createTempFileName($prefix)
    {
        $tempDir = '';

        if (!is_dir($tempDir)) {
            @mkdir($tempDir);
        }

        return tempnam($tempDir, $prefix);
    }
}
