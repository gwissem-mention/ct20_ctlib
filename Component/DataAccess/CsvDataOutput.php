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
     * default maximum memory buffer size 1 Megabyte
     */
    const DEFAULT_BUFFER_SIZE = 1;

    /**
     * store column header names
     * @var array
     */
    protected $columns = [];

    /**
     * store buffer size in megabyte
     * @var integer
     */
    protected $bufferSize;

    /**
     * store csv file handler
     * @var mixed
     */
    protected $fileHandle;

    /**
     * CsvDataOutput constructor.
     * @param null $columns
     * @param null $bufferSize
     */
    public function __construct(
        $columns = null,
        $bufferSize = null)
    {
        $this->columns = $columns;
        $this->bufferSize = $bufferSize ?: self::DEFAULT_BUFFER_SIZE;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $fields
     *
     */
    public function start(array $fields)
    {
        //memory size needs to be in bytes
        $memorySize = $this->bufferSize * 1048576;
        //allocate file output buffer in memory
        $this->fileHandle = fopen('php://temp/maxmemory:' . $memorySize, 'w');

        if (!$this->fileHandle) {
            throw new \Exception("CSV file buffer creation failed");
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
