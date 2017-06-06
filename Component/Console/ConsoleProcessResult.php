<?php
namespace CTLib\Component\Console;

/**
 * Defines a console process result so that it can be output in a standard way
 * using ConsoleOutputHelper#outputProcessResult.
 * @author Mike Turoff
 */
class ConsoleProcessResult
{
    /**
     * Default result messages.
     */
    const DEFAULT_SUCCESS_MESSAGE = "SUCCESS";
    const DEFAULT_FAILURE_MESSAGE = "FAILED";

    /**
     * @var string
     * Name of process.
     */
    protected $processName;

    /**
     * @var boolean
     * Indicates whether process completed successfully.
     */
    protected $success;

    /**
     * @var string
     * Message describing process result.
     */
    protected $message;


    /**
     * @param string $processName
     */
    public function __construct($processName)
    {
        $this->processName = $processName;
        $this->success();
    }

    /**
     * Gives process result success status.
     * @param string $message
     * @return ConsoleProcessResult
     */
    public function success($message = self::DEFAULT_SUCCESS_MESSAGE)
    {
        $this->success = true;
        $this->message = $message;
        return $this;
    }

    /**
     * Gives process result failure status.
     * @param string $message
     * @return ConsoleProcessResult
     */
    public function failure($message = self::DEFAULT_FAILURE_MESSAGE)
    {
        $this->success = false;
        $this->message = $message;
        return $this;
    }

    /**
     * Returns process name.
     * @return string
     */
    public function getProcessName()
    {
        return $this->processName;
    }

    /**
     * Indicates whether successful result.
     * @return boolean
     */
    public function isSuccess()
    {
        return $this->success;
    }

    /**
     * Returns process result message.
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

}
