<?php
namespace CTLib\Util;

/**
 * Executes shell commands.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class CommandExecutor
{

    /**
     * @var string
     */
    protected $commandPath;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $arguments;

    /**
     * @var array
     */
    protected $options;
    
    /**
     * @param string $commandPath   Absolute path to command script.
     * @param Logger $logger
     */
    public function __construct($commandPath, $logger=null)
    {
        $this->commandPath  = $commandPath;
        $this->logger       = $logger;
        $this->arguments    = array();
        $this->options      = array();
    }

    /**
     * Adds argument to execution.
     *
     * @param mixed $value
     * @return CommandExecutor($this)
     */
    public function addArgument($value)
    {
        $this->arguments[] = $value;
        return $this;
    }

    /**
     * Adds option to execution.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return CommandExecutor($this)
     */
    public function addOption($name, $value=null)
    {
        $this->options[] = array($name, $value);
        return $this;
    }

    /**
     * Resets execution arguments.
     *
     * @return CommandExecutor($this)
     */
    public function resetArguments()
    {
        $this->arguments = array();
        return $this;
    }

    /**
     * Resets execution options.
     *
     * @return CommandExecutor($this)
     */
    public function resetOptions()
    {
        $this->options = array();
        return $this;
    }

    /**
     * Executes command.
     *
     * @param string $host  If provided, will use SSH to execute command on a
     *                      remote server.
     * @param string $user  Optional username when executing remote command.
     *
     * @return array        Execution output buffer.
     * @throws Exception    If command returns non-zero status.
     */
    public function exec($host=null, $user=null)
    {
        $cmd = $this->formatCommand($host, $user);

        if ($this->logger) {
            $this->logger->addDebug("exec: {$cmd}");
        }

        exec($cmd, $output, $status);

        if ($this->logger) {
            $this->logger->addDebug("exec: {$cmd} exited with status {$status} and output:\n" . join("\n", $output));
        }

        if ($status !== 0) {
            throw new \Exception(
                "COMMAND: {$cmd}" .
                "\nSTATUS: {$status}" . 
                "\nOUTPUT:\n" .
                join("\n", $output)
            );
        }
        return $output;
    }

    /**
     * Executes command asynchronously (forces into background).
     *
     * @param string $host  If provided, will use SSH to execute command on a
     *                      remote server.
     * @param string $user  Optional username when executing remote command.
     *
     * @return void
     */
    public function execAsynchronous($host=null, $user=null)
    {
        $cmd = $this->formatCommand($host, $user) . ' >> /dev/null &';

        if ($this->logger) {
            $this->logger->addDebug("exec async: {$cmd}");
        }
        exec($cmd);
    }

    /**
     * Formats complete command execution string.
     *
     * @param string $host  If provided, will use SSH to execute command on a
     *                      remote server.
     * @param string $user  Optional username when executing remote command.
     *
     * @return string
     */
    protected function formatCommand($host=null, $user=null)
    {
        $cmd = $this->commandPath
             . ' ' . $this->formatArguments()
             . ' ' . $this->formatOptions();
        
        if ($host) {
            $cmd = $this->formatSSH($host, $user) . ' ' . $cmd;
        }
        return $cmd;
    }

    /**
     * Formats execution arguments.
     *
     * @return string
     */
    protected function formatArguments()
    {
        return array_reduce(
            $this->arguments,
            function($r, $arg) {
                $r .= ' ' . escapeshellarg($arg);
                return $r;
            },
            ''
        );
    }

    /**
     * Formats execution options.
     *
     * @return string
     */
    protected function formatOptions()
    {
        return array_reduce(
            $this->options,
            function($r, $option) {
                list($name, $value) = $option;
                if (strlen($name) == 1 && ! $value) {
                    $r .= " -{$name}";
                } else {
                    $r .= " --{$name}";
                    if ($value) {
                        $r .= "=" . escapeshellarg($value);
                    }    
                }
                return $r;
            },
            ''
        );
    }


}