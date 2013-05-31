<?php
namespace CTLib\Component\DataProvider;

/**
 * class to output downloadable csv file for data provider
 *
 * @author Shuang Liu <sliu@celltrak.com>
 */
class CsvRecordProcessor implements RecordProcessorInterface
{
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
     * {@inheritdoc}
     */
    public function getTotal($queryBuilder)
    {
        return -1;
    }
    
    /**
     * {@inheritdoc}
     */
    public function beforeProcessRecord($model)
    {
        $this->fileName   = tempnam(sys_get_temp_dir(), "rst");
        $this->fileHandle = fopen($this->fileName, "w");

        if (!$this->fileHandle) {
            throw new \Exception("csv file creation failed");
        }

        //write csv header
        fputcsv($this->fileHandle, $model->aliases);
    }
    
    /**
     * {@inheritdoc}
     */
    public function processRecord($entity, $record, $model)
    {
        // convert array in each record into string
        $record = array_map(
            function ($item) {
                if (!is_array($item)) {
                    return $item;
                }
                return implode("|", $item);
            },
            $record
        );

        fputcsv($this->fileHandle, $record);

        unset($record);
        gc_enable(); // Enable Garbage Collector
        gc_collect_cycles();
        gc_disable();
    }

    /**
     * {@inheritdoc}
     */
    public function getRecordResult($queryConfig)
    {
        return $this->fileName;
    }
    
    /**
     * {@inheritdoc}
     */
    public function formatResult($total, $model, $data)
    {
        fclose($this->fileHandle);
        return $this->fileName;
    }

}