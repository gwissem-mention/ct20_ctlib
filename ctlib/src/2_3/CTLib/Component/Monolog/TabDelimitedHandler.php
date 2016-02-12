<?php
namespace CTLib\Component\Monolog;

use CTLib\Component\Monolog\Logger,
    CTLib\Util\Arr;

/**
 * Custom tab-delimited file handler for Monolog.
 *
 * NOTE: Currently hard-coded to create new file each day.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class TabDelimitedHandler extends \Monolog\Handler\AbstractProcessingHandler
{

    const BUFFER_LIMIT      = 100;
    const VALUE_DELIM       = "\t";
    const LINE_DELIM        = "\n---\n";


    /**
     * @var string
     */
    protected $pathPrefix;

    /**
     * @var string
     */
    protected $logDir;

    /**
     * @var array
     */
    protected $buffer;

    /**
     * @var boolean
     */
    protected $initialized;

    
    /**
     * @param string $rootDir
     * @param string $logDir
     * @param integer $level        See Monolog documentation.
     * @param boolean $bubble       See Monolog documentation.
     */
    public function __construct(
                        $rootDir,
                        $logDir,
                        $level=Logger::DEBUG,
                        $bubble=true)
    {
        if (! is_int($level)) {
            $level = constant(
                '\CTLib\Component\Monolog\Logger::' . strtoupper($level)
            );
        }
        parent::__construct($level, (bool) $bubble);
        
        // Remove /app from end of root directory path. We'll use this to strip
        // away redundant root path from log messages.
        $this->pathPrefix   = substr($rootDir, 0, -3);
        $this->logDir       = $logDir;
        $this->buffer       = array();
        $this->initialized  = false;
    }

    /**
     * Flushes any remaining lines in buffer.
     */
    public function __destruct()
    {
        $this->flushBuffer();
    }

    /**
     * Writes log record.
     *
     * @param array $record
     * @return void
     */
    protected function write(array $record)
    {
        $file = Arr::findByKeyChain($record, 'extra.file');
        $file = str_replace($this->pathPrefix, '', $file);

        $values = array(
            gethostname(),
            $record['channel'],
            $record['level'],
            $record['datetime']->format('Y-m-d H:i:s'),
            $record['datetime']->format('U'),
            $record['message'],
            Arr::findByKeyChain($record, 'context._thread'),
            $file,
            Arr::findByKeyChain($record, 'extra.line'),
            Arr::findByKeyChain($record, 'extra.class'),
            Arr::findByKeyChain($record, 'extra.function'),
            Arr::findByKeyChain($record, 'extra.environment'),
            Arr::findByKeyChain($record, 'extra.exec_mode'),
            Arr::findByKeyChain($record, 'extra.brand_id'),
            Arr::findByKeyChain($record, 'extra.site_id'),
            Arr::findByKeyChain($record, 'extra.app_version'),
            Arr::findByKeyChain($record, 'extra.app_platform'),
            Arr::findByKeyChain($record, 'extra.app_modules')
        );

        $this->buffer[] = $values;

        if ($record['level'] >= Logger::INFO
            || count($this->buffer) >= self::BUFFER_LIMIT) {
            $this->flushBuffer();
        }
    }

    /**
     * Flushes lines in buffer to file.
     *
     * @return void
     */
    private function flushBuffer()
    {
        if (! $this->buffer) {
            // Nothing in buffer to flush.
            return;
        }

        $logFilename    = $this->getLogFilename();
        $logPath        = $this->logDir . '/' . $logFilename;

        if (! $this->initialized) {
            $logIsNew = $this->initialize($logPath);
        } else {
            $logIsNew = false;
        }

        $contents = array_reduce(
                        $this->buffer,
                        function($contents, $lineValues) {
                            $contents .= join(self::VALUE_DELIM, $lineValues)
                                       . self::LINE_DELIM;
                            return $contents;
                        },
                        '');
        @file_put_contents($logPath, $contents, \FILE_APPEND);
        
        if ($logIsNew) {
            @chmod($logPath, 0660);    
        }
        
        $this->buffer = array();
    }

    /**
     * Prepares log for writing.
     *
     * @param string $logPath
     * @return boolean  Indicates whether log path exists.
     */
    protected function initialize($logPath)
    {
        if (! is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
            $logIsNew = true;
        } else {
            $logIsNew = ! @file_exists($logPath);
        }
        $this->initialized = true;
        return $logIsNew;
    }

    /**
     * Returns name of log file.
     *
     * @return string
     */
    private function getLogFilename()
    {
        return 'log.' . strtolower(date('d-M-Y') . '-' . gethostname());
    }

}