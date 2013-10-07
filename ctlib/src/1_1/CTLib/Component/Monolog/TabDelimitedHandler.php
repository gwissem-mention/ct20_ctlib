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
     * @var object
     */
    protected $logFile;

    /**
     * @var array
     */
    protected $buffer;

    
    /**
     * @param AppKernel $kernel
     * @param integer $level        See Monolog documentation.
     * @param boolean $bubble       See Monolog documentation.
     */
    public function __construct($kernel, $level=Logger::DEBUG, $bubble=true)
    {
        if (! is_int($level)) {
            $level = constant(
                '\CTLib\Component\Monolog\Logger::' . strtoupper($level)
            );
        }
        parent::__construct($level, (bool) $bubble);
        
        // Remove /app from end of root directory path. We'll use this to strip
        // away redundant root path from log messages.
        $this->pathPrefix   = substr($kernel->getRootDir(), 0, -3);
        $this->logFile      = null;

        if ($kernel->getContainer()->hasParameter('tab_delimited_log_path')) {
            $this->logDir = $kernel
                            ->getContainer()
                            ->getParameter('tab_delimited_log_path');
        } else {
            $this->logDir = $kernel->getLogDir();
        }

        $this->buffer = array();
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

        if (count($this->buffer) >= self::BUFFER_LIMIT) {
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

        if ($this->logFile === false) {
            // Already tried to open log file but couldn't. Just truncate buffer.
            $this->buffer = array();
            return;
        }

        if (! $this->logFile) {
            $this->logFile = $this->initialize();

            if ($this->logFile === false) {
                // Couldn't open log file.
                $this->buffer = array();
                return;
            }
        }

        $contents = array_reduce(
                        $this->buffer,
                        function($contents, $lineValues) {
                            $contents .= join(self::VALUE_DELIM, $lineValues)
                                       . self::LINE_DELIM;
                            return $contents;
                        },
                        '');
        fwrite($this->logFile, $contents);
        $this->buffer = array();
    }

    /**
     * Initializes connection to SQLite database.
     *
     * @return void
     */
    private function initialize()
    {
        $filename = 'log.' . strtolower(date('d-M-Y') . '-' . gethostname());
        $filepath = $this->logDir . '/' . $filename;
        return fopen($filepath, 'a');
    }

}