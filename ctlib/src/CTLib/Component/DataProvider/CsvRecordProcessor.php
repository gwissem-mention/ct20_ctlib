<?php
namespace CTLib\Component\DataProvider;

/**
 * class to output downloadable csv file for data provider
 *
 * @author Shuang Liu <sliu@celltrak.com>
 */
class CsvRecordProcessor implements RecordProcessorInterface
{
    const DOWNLOAD_DIR = "download";
    const DELETE_THRESHOLD_SECOND = 3600;

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
     * kernel
     * @var AppKernel 
     */
    protected $kernel;
    
    
    public function __construct($kernel)
    {
        $this->kernel = $kernel;
    }

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
        $cacheDir = $this->kernel->getCacheDir();
        
        $this->removeOutdatedFiles();
        $this->fileName   = $this->createTempFileName();
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

    /**
     * create Temp File
     *
     * @return string the name of created temporary file
     *
     */
    protected function createTempFileName()
    {
        $tempDir = $this->kernel->getCacheDir() . "/" . static::DOWNLOAD_DIR;
        if (!is_dir($tempDir)) {
            @mkdir($tempDir);
        }
        return tempnam($tempDir, "rst");
    }

    /**
     * Remove files that are older than the threshold
     *
     * @return void
     *
     */    
    protected function removeOutdatedFiles()
    {
        $tempDir = $this->kernel->getCacheDir() . "/" . static::DOWNLOAD_DIR;

        $files = scandir($tempDir);
        foreach($files as $file) {
            $filePath = $tempDir . "/" . $file;
            if(is_file($filePath)
                && time() - filemtime($filePath) >= static::DELETE_THRESHOLD_SECOND
            ) {
                @unlink($filePath);
            }
        }
    }
}