<?php
namespace CTLib\Component\Monolog;

use CTLib\Component\Monolog\Logger,
    CTLib\Util\Arr;

/**
 * Custom SQLite handler for Monolog.
 *
 * NOTE: Currently hard-coded to create new SQLite log database file each day.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class SqliteHandler extends \Monolog\Handler\AbstractProcessingHandler
{
    /**
     * @var string
     */
    protected $pathPrefix;

    /**
     * @var string
     */
    protected $logDir;

    /**
     * @var boolean
     */
    protected $initialized;

    /**
     * @var boolean
     */
    protected $useFailover;


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
        $this->logDir       = $kernel->getLogDir();
        $this->initialized  = false;
        $this->useFailover  = false;
    }

    /**
     * Writes log record.
     *
     * @param array $record
     * @return void
     */
    protected function write(array $record)
    {
        if (! $this->initialized) {
            $this->initialize();
        }

        if ($this->useFailover) {
            // Couldn't connect to SQLite database during initalization.
            return;
        }

        $file = Arr::findByKeyChain($record, 'extra.file');
        $file = str_replace($this->pathPrefix, '', $file);

        $this->statement->execute(array(
            'channel'       => $record['channel'],
            'level'         => $record['level'],
            'datetime'      => $record['datetime']->format('Y-m-d H:i:s'),
            'ts'            => $record['datetime']->format('U'),
            'message'       => $record['message'],
            'thread'        => Arr::findByKeyChain($record, 'context._thread'),
            'file'          => $file,
            'line'          => Arr::findByKeyChain($record, 'extra.line'),
            'class'         => Arr::findByKeyChain($record, 'extra.class'),
            'method'        => Arr::findByKeyChain($record, 'extra.function'),
            'environment'   => Arr::findByKeyChain($record, 'extra.environment'),
            'exec_mode'     => Arr::findByKeyChain($record, 'extra.exec_mode'),
            'brand_id'      => Arr::findByKeyChain($record, 'extra.brand_id'),
            'site_id'       => Arr::findByKeyChain($record, 'extra.site_id'),
            'app_version'   => Arr::findByKeyChain($record, 'extra.app_version'),
            'app_platform'  => Arr::findByKeyChain($record, 'extra.app_platform'),
            'app_modules'   => Arr::findByKeyChain($record, 'extra.app_modules')
        ));
    }

    /**
     * Initializes connection to SQLite database.
     *
     * @return void
     */
    private function initialize()
    {
        try {
            $this->pdo = new \PDO($this->getConnectionString());
            $this->pdo->exec($this->getSchemaSql());
            $this->pdo->exec("PRAGMA synchronous = 0;");
            $this->statement = $this->pdo->prepare($this->getInsertStatement());
        } catch (\PdoException $e) {
            $this->useFailover = true;
            $this->bubble = true;
        }
        $this->initialized = true;
    }

    /**
     * Returns connection string for SQLite database.
     *
     * @return string
     */
    protected function getConnectionString()
    {
        $filename = strtolower(date('d-M-Y')) . '-log.sqlite';
        return "sqlite:{$this->logDir}/{$filename}";
    }

    /**
     * Returns log database schema SQL.
     *
     * @return sql
     */
    private function getSchemaSql()
    {
        return <<<SQL
            CREATE TABLE IF NOT EXISTS log (
                channel TEXT,
                level INTEGER,
                datetime TEXT,
                thread TEXT,
                message TEXT,
                file TEXT,
                line TEXT,
                class TEXT,
                method TEXT,
                brand_id TEXT,
                site_id TEXT,
                app_version TEXT,
                app_platform TEXT,
                app_modules TEXT,
                environment TEXT,
                exec_mode TEXT,
                ts INTEGER
            );
SQL;
    }

    /**
     * Returns log INSERT statement SQL.
     *
     * @return string
     */
    private function getInsertStatement()
    {
        return <<<SQL
            INSERT INTO log (
                channel,
                level,
                datetime,
                thread,
                message,
                file,
                line,
                class,
                method,
                brand_id,
                site_id,
                app_version,
                app_platform,
                app_modules,
                environment, 
                exec_mode,
                ts
            ) VALUES (
                :channel,
                :level,
                :datetime,
                :thread,
                :message,
                :file,
                :line,
                :class,
                :method,
                :brand_id,
                :site_id,
                :app_version,
                :app_platform,
                :app_modules,
                :environment, 
                :exec_mode,
                :ts
            )
SQL;
    }


}