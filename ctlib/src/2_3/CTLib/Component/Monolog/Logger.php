<?php
namespace CTLib\Component\Monolog;

/**
 * Custom wrapper to Monolog\Logger in order to streamline implementation of
 * logger thread (see Logger::startThread for more).
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class Logger extends \Monolog\Logger implements \Symfony\Component\HttpKernel\Log\LoggerInterface
{
    
    /**
     * @var string
     *
     * Thread shared by all Logger instances (channels).
     * See Logger::startThread for more.
     */
    protected static $thread = null;

    /**
     * Starts new logger thread.
     *
     * All log messages added will be tagged with thread while it's active. The
     * thread is static (shared by all Logger instances a.k.a. channels) so that
     * the message is tagged properly regardless of the channel to which its
     * assigned.
     *
     * @return void
     */
    public function startThread()
    {
        self::$thread = uniqid('', true);
    }

    /**
     * Stops active logger thread.
     *
     * @return void
     */
    public function stopThread()
    {
        self::$thread = null;
    }

    /**
     * Returns active logger thread.
     *
     * @return string|null
     */
    public function getThread()
    {
        return self::$thread;
    }

    /**
     * Adds record to log.
     *
     * Extend from default version in Monlog\Logger so we can automatically
     * append logger thread into message's context.
     *
     * @inherit
     */
    public function addRecord($level, $message, array $context=array())
    {
        $context['_thread'] = self::$thread;
        return parent::addRecord($level, $message, $context);
    }

    /**
     * Alias for Monlog\Logger::err.
     *
     * @param string $message
     * @param array $context
     */
    public function error($message, array $context=array())
    {
        return $this->err($message, $context);
    }

    /**
     * Alias for Monlog\Logger::crit.
     *
     * @param string $message
     * @param array $context
     */
    public function critical($message, array $context=array())
    {
        return $this->crit($message, $context);
    }


}