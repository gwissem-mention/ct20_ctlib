<?php
namespace CTLib\Component\DataProvider;

/**
 * class to process downloadable results for recordset
 *
 * @author Shuang Liu <sliu@celltrak.com>
 */
abstract class DownloadRecordProcessor implements RecordProcessorInterface
{
    const DOWNLOAD_DIR = "download";
    const DELETE_THRESHOLD_SECOND = 3600;

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
     * create Temp File
     *
     * @return string the name of created temporary file
     *
     */
    protected function createTempFileName($prefix)
    {
        $tempDir = $this->kernel->getCacheDir() . "/" . static::DOWNLOAD_DIR;

        $this->removeOutdatedFiles($tempDir);

        if (!is_dir($tempDir)) {
            @mkdir($tempDir);
        }

        return tempnam($tempDir, $prefix);
    }

    /**
     * Remove files that are older than the threshold
     *
     * @return void
     *
     */    
    private function removeOutdatedFiles($tempDir)
    {
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

    /**
     * Help garbage collect memory
     *
     * @return void 
     *
     */
    protected function recycleMemoryGarbage()
    {
        gc_enable(); // Enable Garbage Collector
        gc_collect_cycles();
        gc_disable();
    }
}
