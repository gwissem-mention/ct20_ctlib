<?php
namespace CTLib\Component\DataProvider;

use CTLib\Component\HttpFoundation\CsvFileResponse;

/**
 * class to output downloadable csv file for data provider
 *
 * @author Shuang Liu <sliu@celltrak.com>
 */
class CsvRecordProcessor extends DownloadRecordProcessor
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
    public function beforeProcessRecord($model)
    {
        $this->fileName   = $this->createTempFileName("rst");
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

        $this->recycleMemoryGarbage();
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
     * {@inheritdoc}
     */
    public function getDataResponse($data)
    {
        $request = $this->kernel->getContainer()->get("request");
        return new CsvFileResponse(
            $request,
            $this->fileName,
            "celltrak" . date("YmdHis") . ".csv"
        );
    }
}