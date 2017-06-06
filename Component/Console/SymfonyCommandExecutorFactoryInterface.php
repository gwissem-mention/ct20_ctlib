<?php
namespace CTLib\Component\Console;

/**
 * Interface for defining factory services that create CommandExecutors.
 * @author Mike Turoff
 */
interface SymfonyCommandExecutorFactoryInterface
{

    /**
     * Creates CommandExecutor for given command name.
     * @param string $commandName
     * @return CommandExecutor
     */
    public function createCommandExecutor($commandName);

}
