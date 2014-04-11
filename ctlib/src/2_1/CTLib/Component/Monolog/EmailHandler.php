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
     * Message templates.
     */
    const STANDARD_TEMPLATE     = '@CTLib/Resources/EmailLogger/standard.html.php';
    const THRESHOLD_TEMPLATE    = '@CTLib/Resources/EmailLogger/threshold.html.php';

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var string
     */
    protected $logDir;

    /**
     * @var string
     */
    protected $standardTemplate;

    /**
     * @var string
     */
    protected $thresholdTemplate;

    /**
     * @var string
     */
    protected $from;

    /**
     * @var array
     */
    protected $defaultTo;

    /**
     * @var integer
     */
    protected $thresholdCount;

    /**
     * @var integer
     */
    protected $thresholdSeconds;

    /**
     * @var integer
     */
    protected $sleepSeconds;

    /**
     * @var array
     */
    protected $routingRules;

    /**
     * @var boolean
     */
    protected $disableDelivery;

    /**
     * @var array
     */
    protected $alwaysSendTo;

    /**
     * @var boolean
     */
    protected $initialized;

    
    /**
     * @param Mailer $mailer
     * @param Kernel $kernel
     * @param string $from
     * @param array $defaultTo
     * @param integer $thresholdCount
     * @param integer $thresholdSeconds
     * @param integer $sleepSeconds
     * @param integer $level        See Monolog documentation.
     * @param boolean $bubble       See Monolog documentation.
     */
    public function __construct(
                        $mailer,
                        $kernel,
                        $from,
                        $defaultTo,
                        $thresholdCount,
                        $thresholdSeconds,
                        $sleepSeconds,
                        $level=Logger::ERROR,
                        $bubble=true)
    {
        if (! is_int($level)) {
            $level = constant(
                '\CTLib\Component\Monolog\Logger::' . strtoupper($level)
            );
        }
        parent::__construct($level, (bool) $bubble);

        $this->mailer               = $mailer;
        $this->logDir               = $kernel->getRootDir() . '/logs/EmailLogger';
        $this->standardTemplate     = $kernel
                                        ->locateResource(self::STANDARD_TEMPLATE);
        $this->thresholdTemplate    = $kernel
                                        ->locateResource(self::THRESHOLD_TEMPLATE);
        $this->from                 = $from;
        $this->defaultTo            = $defaultTo;
        $this->thresholdCount       = $thresholdCount;
        $this->thresholdSeconds     = $thresholdSeconds;
        $this->sleepSeconds         = $sleepSeconds;
        $this->routingRules         = array();
        $this->disableDelivery      = false;
        $this->alwaysSendTo         = array();
        $this->initialized          = false;
    }

    /**
     * Sets disableDelivery.
     *
     * @param boolean $disableDelivery
     * @return void
     */
    public function setDisableDelivery($disableDelivery)
    {
        $this->disableDelivery = $disableDelivery;
    }

    /**
     * Sets alwaysSendTo.
     *
     * @param array $alwaysSendTo
     * @return void
     */
    public function setAlwaysSendTo($alwaysSendTo)
    {
        $this->alwaysSendTo = $alwaysSendTo;
    }

    /**
     * Adds email routing rule.
     *
     * @param string $key
     * @param string $needle
     * @param array $to
     *
     * @return void
     */
    public function addRoutingRule($key, $needle, $to)
    {
        $this->routingRules[] = array(
                                    'key'       => $key,
                                    'needle'    => $needle,
                                    'to'        => $to);
    }

    /**
     * If applicable, sends log record email message.
     *
     * @param array $record
     * @return void
     */
    public function write(array $record)
    {
        if ($this->disableDelivery) {
            return;
        }        

        $to = $this->getTo($record);

        if (! $to) {
            // Configured to suppress email delivery.
            return;
        }

        if ($this->alwaysSendTo) {
            $to = $this->alwaysSendTo;
        }

        if (! $this->initialized) {
            $this->initialize();   
        }

        $logPath        = $this->getLogPath($record);
        $allLogEntries  = $this->getLogEntries($logPath);
        $logKey         = join(",", $to);
        $logEntries     = Arr::get($logKey, $allLogEntries, array());
        $now            = time();

        if (isset($record['extra']['site_name'])) {
            $record['__site__'] = $record['extra']['site_name'];
        } else {
            $record['__site__'] = $record['extra']['brand_id'] . ' Gateway';
        }

        $record['__hostname__'] = gethostname();

        // Determine which email to send (if any) based on the number of log
        // entries.
        $logCount = count($logEntries);

        if ($this->thresholdCount <= 0 || $logCount < $this->thresholdCount) {
            // Continue sending standard messages.
            $this->sendStandardMessage($to, $record);
            $logEntries[] = $now;
        } elseif (strpos(end($logEntries), 'sleep@') === 0) {
            // Previously reached threshold and entered sleep mode. See if it's
            // time to resume emailing.
            $sleepTime = substr(end($logEntries), 6);

            if ($now - $sleepTime < $this->sleepSeconds) {
                // Still within sleep window so continue snoozing.
                return;
            } else {
                // Done napping. Send standard message and reset log entries.
                $this->sendStandardMessage($to, $record);
                $logEntries = array($now);
            }
        } else {
            // This message meets or exceeds threshold count. Determine whether
            // all entries occurred within threshold window, which would put us
            // into sleep mode.
            if ($now - current($logEntries) <= $this->thresholdSeconds) {
                // Oldest logged message was within threshold window so we
                // need to send threshold message and enter sleep mode.
                $this->sendThresholdMessage($to, $record);
                $logEntries[] = 'sleep@' . $now;
            } else {
                // Oldest logged message occurred outside threshold window
                // so we send normal message and reset log entries.
                $this->sendStandardMessage($to, $record);
                $logEntries = array($now);
            }
        }

        $allLogEntries[$logKey] = $logEntries;
        $this->updateLogFile($logPath, $allLogEntries);
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
     * Initializes email log for writing.
     *
     * @return void
     */
    protected function initialize()
    {
        if (! is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        $this->initialized = true;
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
     * Returns email log entries.
     *
     * @param string $logPath
     * @return array
     */
    protected function getLogEntries($logPath)
    {
        $contents = @file_get_contents($logPath);

        if (! $contents) {
            return array();
        }

        return json_decode($contents, true) ?: array();
    }

    /**
     * Updates and closes email log file.
     *
     * @param string $logPath
     * @param array $logEntries
     *
     * @return void
     */
    protected function updateLogFile($logPath, $logEntries)
    {
        @file_put_contents($logPath, json_encode($logEntries));
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
        $subject = "ERROR - {$record['__site__']}";

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
        $subject = "HIGH VOLUME OF ERRORS - {$record['__site__']}";

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