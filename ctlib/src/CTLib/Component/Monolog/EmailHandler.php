<?php
namespace CTLib\Component\Monolog;


use CTLib\Component\Monolog\Logger,
    CTLib\Util\Arr;


/**
 * Custom email handler for Monolog.
 *
 * SAMPLE CONFIGURATION:
 *
 *  ct_lib:
 *      email_logger:
 *          threshold_count: 5  ### OPTIONAL, Number of errors within threshold_seconds before sleep
 *          threshold_seconds: 120  ### OPTIONAL, Length of threshold window
 *          sleep_seconds: 600  ### OPTIONAL, Length of delay before resuming emails
 *          from: alert@celltrak.net    ### REQUIRED!
 *          default_to: [mturoff@celltrak.com]  ### REQUIRED!
 *          rules:  ### OPTIONAL
 *              - { key: message, needle: uncaught exception, to: ~ }   ### Suppresses email
 *              - { key: message, needle: SQLSTATE, to: [mike.turoff@gmail.com] }
 *
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class EmailHandler extends \Monolog\Handler\AbstractProcessingHandler
{
    
    /**
     * Defaults used when not configured.
     */
    const DEFAULT_THRESHOLD_COUNT   = 5;
    const DEFAULT_THRESHOLD_SECONDS = 120;
    const DEFAULT_SLEEP_SECONDS     = 600;

    /**
     * Message templates.
     */
    const STANDARD_TEMPLATE     = '@CTLib/Resources/EmailLogger/standard.html.php';
    const THRESHOLD_TEMPLATE    = '@CTLib/Resources/EmailLogger/threshold.html.php';


    /**
     * @param Container $container
     * @param integer $level    Minimum log level included within this handler.
     * @param boolean $bubble   Whether to bubble log record to subsequent handler.
     */
    public function __construct($container, $level=Logger::ERROR, $bubble=true)
    {
        if (! is_int($level)) {
            $level = constant(
                '\CTLib\Component\Monolog\Logger::' . strtoupper($level)
            );
        }
        parent::__construct($level, (bool) $bubble);

        $this->mailer           = $container->get('mailer');
        $this->logDir           = $container
                                    ->get('kernel')->getRootDir()
                                    . '/logs/EmailLogger';
        $this->standardTemplate = $container
                                    ->get('kernel')
                                    ->locateResource(self::STANDARD_TEMPLATE);
        $this->thresholdTemplate = $container
                                    ->get('kernel')
                                    ->locateResource(self::THRESHOLD_TEMPLATE);
        $this->from             = $this->getParam($container, 'from');
        $this->defaultTo        = $this->getParam($container, 'default_to');
        $this->routingRules     = $this->getParam($container, 'rules');
        $this->thresholdCount   = $this->getParam($container, 'threshold_count')
                                  ?: self::DEFAULT_THRESHOLD_COUNT;
        $this->thresholdSeconds = $this->getParam($container, 'threshold_seconds')
                                  ?: self::DEFAULT_THRESHOLD_SECONDS;
        $this->sleepSeconds     = $this->getParam($container, 'sleep_seconds')
                                  ?: self::DEFAULT_SLEEP_SECONDS;
    }

    /**
     * Returns value for container parameter.
     *
     * @param Container $container
     * @param string $paramter
     *
     * @return mixed
     * @throws Exception    If parameter not set.
     */
    protected function getParam($container, $parameter)
    {
        return $container->getParameter("ctlib.email_logger.{$parameter}");
    }

    /**
     * If applicable, sends log record email message.
     *
     * @param array $record
     * @return void
     */
    public function write(array $record)
    {
        $to = $this->getTo($record);

        if (is_null($to)) {
            // Configured to suppress email delivery.
            return;
        }

        $logPath        = $this->getLogPath($record);
        $logFile        = $this->openLogFile($logPath);
        $allLogEntries  = $this->getLogEntries($logFile, $logPath);
        $logKey         = join(",", $to);
        $logEntries     = Arr::get($logKey, $allLogEntries, array());
        $now            = time();

        if (isset($record['extra']['site_name'])) {
            $record['site'] = $record['extra']['site_name'];
        } else {
            $record['site'] = $record['extra']['brand_id'] . ' Gateway';
        }

        // Determine which email to send (if any) based on the number of log
        // entries.
        switch (count($logEntries)) {
            case $this->thresholdCount + 1:
                // Previously reached threshold, sent threshold email and
                // entered sleep mode. Determine whether we should continue to
                // sleep or resume emailing.
                if ($now - end($logEntries) < $this->sleepSeconds) {
                    // Still within sleep window so continue snoozing.
                    return;
                } else {                    
                    // Exited sleep window. Send standard message and reset
                    // log entries.
                    $this->sendStandardMessage($to, $record);
                    $logEntries = array($now);
                }
                break;

            case $this->thresholdCount:
                // Last message hit threshold count. Determine whether all
                // entries occurred within threshold window, which would put us
                // into sleep mode.
                if ($now - current($logEntries) <= $this->thresholdSeconds) {
                    // Oldest logged message was within threshold window so we
                    // need to send threshold message and enter sleep mode.
                    $this->sendThresholdMessage($to, $record);
                    $logEntries[] = $now;
                } else {
                    // Oldest logged message occurred outside threshold window
                    // so we send normal message and adjust entries accordingly.
                    $this->sendStandardMessage($to, $record);
                    array_shift($logEntries);
                    $logEntries[] = $now;
                }
                break;

            default:
                // Continue sending standard messages.
                $this->sendStandardMessage($to, $record);
                $logEntries[] = $now;
        }

        $allLogEntries[$logKey] = $logEntries;
        $this->updateLogFile($logFile, $allLogEntries);
    }

    /**
     * Returns to addresses assigned to receive record.
     *
     * @param array $record
     * @return array|null   Returns NULL if no one should be emailed.
     */
    protected function getTo($record)
    {
        foreach ($this->routingRules as $rule) {
            if (! isset($record[$rule['key']])) {
                continue;
            }

            if (stripos($record[$rule['key']], $rule['needle']) !== false) {
                return $rule['to'];
            }
        }
        return $this->defaultTo;
    }

    /**
     * Returns path to email log file.
     *
     * @param array $record
     * @return string 
     */
    protected function getLogPath($record)
    {
        if (isset($record['extra']['site_id'])) {
            return $this->logDir . '/' . $record['extra']['site_id'];
        } elseif (isset($record['extra']['brand_id'])) {
            return $this->logDir . '/' . $record['extra']['brand_id'];
        } else {
            return $this->logDir . '/default';
        }
    }

    /**
     * Opens email log file and acquires exclusive lock on it.
     *
     * @param string $logPath
     * @return object|false     Returns FALSE if couldn't open file or couldn't
     *                          acquire lock.
     */
    protected function openLogFile($logPath)
    {
        $logFile = @fopen($logPath, 'r+');

        if (! $logFile) {
            if (@mkdir($this->logDir, 0755, true)) {
                $logFile = @fopen($logPath, 'r+');
            }
        }

        // Attempt to get exclusive lock on file so we don't have multiple
        // concurrent threads overwriting each other.
        if (! $logFile || ! flock($logFile, \LOCK_EX)) {
            return false;
        }
        return $logFile;
    }

    /**
     * Returns email log entries.
     *
     * @param mixed $logFile
     * @param string $logPath
     *
     * @return array
     */
    protected function getLogEntries($logFile, $logPath)
    {
        if ($logFile === false) {
            return array();
        }

        $filesize = filesize($logPath);

        if ($filesize === 0) {
            return array();
        }

        $contents = fread($logFile, $filesize);
        return json_decode($contents, true) ?: array();
    }

    /**
     * Updates and closes email log file.
     *
     * @param mixed $logFile
     * @param array $logEntries
     *
     * @return void
     */
    protected function updateLogFile($logFile, $logEntries)
    {
        if ($logFile === false) {
            return;
        }

        $contents = json_encode($logEntries);
        fwrite($logFile, $contents);
        flock($logFile, \LOCK_UN);
        fclose($logFile);
    }

    /**
     * Sends standard log message.
     *
     * @param array $to
     * @param array $record
     *
     * @return void
     */
    protected function sendStandardMessage($to, $record)
    {
        $subject    = $this->formatStandardSubject($record);
        $html       = $this->formatHtml($record, $this->standardTemplate);

        $message = $this
                    ->mailer
                    ->createMessage()
                    ->setTo($to)
                    ->setFrom($this->from)
                    ->setSubject($subject)
                    ->setBody(json_encode($record))
                    ->addPart($html, 'text/html');
        $this->mailer->send($message);
    }

    /**
     * Sends threshold reached message.
     *
     * @param array $to
     * @param array $record
     *
     * @return void
     */
    protected function sendThresholdMessage($to, $record)
    {
        $record['thresholdCount']   = $this->thresholdCount;
        $record['thresholdMinutes'] = $this->thresholdSeconds / 60;
        $record['sleepMinutes']     = $this->sleepSeconds / 60;

        $subject    = $this->formatThresholdSubject($record);
        $html       = $this->formatHtml($record, $this->thresholdTemplate);

        $message = $this
                    ->mailer
                    ->createMessage()
                    ->setTo($to)
                    ->setFrom($this->from)
                    ->setSubject($subject)
                    ->addPart($html, 'text/html');
        $this->mailer->send($message);
    }

    /**
     * Formats subject for standard message.
     *
     * @param array $record
     * @return string
     */
    protected function formatStandardSubject($record)
    {
        $subject = "ERROR - {$record['site']}";

        if ($record['extra']['exec_mode'] == 'prod') {
            $subject = "PRODUCTION - {$subject}";
        }
        return $subject;
    }

    /**
     * Formats subject for threshold message.
     *
     * @param array $record
     * @return string
     */
    protected function formatThresholdSubject($record)
    {
        $subject = "HIGH VOLUME OF ERRORS - {$record['site']}";

        if ($record['extra']['exec_mode'] == 'prod') {
            $subject = "PRODUCTION - {$subject}";
        }
        return $subject;

    }

    /**
     * Formats HTML body.
     *
     * @param array $record
     * @param string $template
     *
     * @return string
     */
    protected function formatHtml($record, $template)
    {
        ob_start();
        include $template;
        return ob_get_clean();
    }


}