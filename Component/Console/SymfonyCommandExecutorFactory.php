<?php
namespace CTLib\Component\Console;

use CTLib\Component\Monolog\Logger;

/**
 * Creates default CommandExecutor for Symfony console commands.
 * @author Mike Turoff
 */
class SymfonyCommandExecutorFactory
    implements SymfonyCommandExecutorFactoryInterface
{

    /**
     * @var string
     */
    protected $rootDir;

    /**
     * @var Logger
     */
    protected $logger;


    /**
     * @param string $rootDir
     * @param Logger $logger
     */
    public function __construct($rootDir, Logger $logger)
    {
        $this->rootDir = $rootDir;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function createCommandExecutor($commandName)
    {
        $commandPath = $this->rootDir . "/console " . $commandName;
        return new CommandExecutor($commandPath, $this->logger);
    }

}
