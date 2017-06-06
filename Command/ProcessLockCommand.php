<?php
namespace CTLib\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CTLib\Component\Console\BaseCommand;
use CTLib\Component\Console\ConsoleTable;
use CTLib\Component\Console\ConsoleOutputHelper;


/**
 * Inspects and removes process locks.
 * @author Mike Turoff
 */
class ProcessLockCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    public function configure()
    {
        parent::configure();

        $this->setDescription('Manages process locks')
            ->addArgument('action', InputArgument::REQUIRED, "Use 'list' to see available actions")
            ->addArgument('consumerId', InputArgument::OPTIONAL, 'Service ID of process lock consumer')
            ->addOption('lockIdParam', 'P', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'Sequenced lock id parameter')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompts');
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init();

        $action = $input->getArgument('action');

        switch ($action) {
            case 'list':
                return $this->execList($input, $output);

            case 'list-consumers':
                return $this->execListConsumers($input, $output);

            case 'find-all-locks':
                return $this->execFindAllLocksForConsumer($input, $output);

            case 'find-lock':
                return $this->execFindLockForConsumer($input, $output);

            case 'remove-lock':
                return $this->execRemoveLockForConsumer($input, $output);

            default:
                throw new \RuntimeException("Invalid action '{$action}'. Use 'list' to see available actions.");
        }

    }

    /**
     * Initializes service variables.
     * TODO replace by defining this command as a service when we upgrade to a
     * current version of Symfony.
     * @return void
     */
    protected function init()
    {
        $container = $this->getContainer();

        $this->processLockManager = $container->get('process_lock.manager');
    }

    /**
     * Lists available command actions.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execList(InputInterface $input, OutputInterface $output)
    {
        $controlActions = [
            'find-all-locks {consumerId}' => 'Finds all locks for consumer',
            'find-lock {consumerId}' => 'Finds specific lock for consumer',
            'remove-lock {consumerId}' => 'Removes specific lock for consumer'
        ];

        $helpActions = [
            'list-consumers' => 'Lists registered consumers',
            'list' => 'Lists these actions'
        ];

        $outputHelper = new ConsoleOutputHelper($output);
        $outputHelper->outputActionList($controlActions, $helpActions);
    }

    /**
     * Lists registered process lock consumers.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execListConsumers(
        InputInterface $input,
        OutputInterface $output
    ) {
        $consumers = $this->processLockManager->getConsumers();

        if (empty($consumers)) {
            $output->writeln("");
            $output->writeln("<fg=red>No registered consumers</>");
            $output->writeln("");
            return;
        }

        $table = new ConsoleTable;
        $table
            ->addColumn('ID', 50)
            ->addColumn('NAME', 35)
            ->addColumn('LOCK Pattern', 50)
            ->addColumn('LOCK TTL', 9);

        foreach ($consumers as $consumerId => $consumer) {
            $table->addRecord(
                $consumerId,
                $consumer->getLockName(),
                $consumer->getLockIdPattern(),
                $consumer->getLockTtl()
            );
        }

        $output->writeln("");
        $table->output($output);
        $output->writeln("");
    }

    /**
     * Inspects all locks for specified consumer.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execFindAllLocksForConsumer(
        InputInterface $input,
        OutputInterface $output
    ) {
        $consumerId = $input->getArgument('consumerId');

        if (empty($consumerId)) {
            throw new \RuntimeException("consumerId is required for 'find-all-locks'");
        }

        $locks = $this->processLockManager->findLocksForConsumer($consumerId);

        if (empty($locks)) {
            $output->writeln("");
            $output->writeln("<fg=red>No Locks Found</>");
            $output->writeln("");
            return;
        }

        $table = new ConsoleTable;
        $table
            ->addColumn('LOCK', 65)
            ->addColumn('TTL', 6);

        foreach ($locks as $lock) {
            $table
                ->addRecord(
                    $lock['key'],
                    $lock['ttl']
                );
        }

        $output->writeln("");
        $table->output($output);
        $output->writeln("");
    }

    /**
     * Inspects single lock for specified consumer based on passed lock ID params.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execFindLockForConsumer(
        InputInterface $input,
        OutputInterface $output
    ) {
        $consumerId = $input->getArgument('consumerId');
        $lockIdParams = $input->getOption('lockIdParam') ?: [];

        if (empty($consumerId)) {
            throw new \RuntimeException("consumerId is required for 'find-lock'");
        }

        $requiredParams = $this->processLockManager
            ->getConsumerLockIdParams($consumerId);

        if ($requiredParams && count($lockIdParams) != count($requiredParams)) {
            throw new \RuntimeException("You must specify lock id params for " . join(', ', $requiredParams));
        }

        $lockIdParams = array_combine($requiredParams, $lockIdParams);
        $lock = $this->processLockManager->findLockForConsumer(
            $consumerId,
            $lockIdParams
        );

        if (empty($lock)) {
            $output->writeln("");
            $output->writeln("<fg=red>No Lock Found</>");
            $output->writeln("");
            return;
        }

        $output->writeln("");
        $outputHelper = new ConsoleOutputHelper($output);
        $outputHelper->outputAttributeValuePair('Lock', $lock['key'], 10);
        $outputHelper->outputAttributeValuePair('TTL', $lock['ttl'], 10);
        $output->writeln("");
    }

    /**
     * Removes single lock for specified consumer based on passed lock id params.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execRemoveLockForConsumer(
        InputInterface $input,
        OutputInterface $output
    ) {
        $consumerId     = $input->getArgument('consumerId');
        $lockIdParams   = $input->getOption('lockIdParam') ?: [];
        $force          = $input->getOption('force');

        if (empty($consumerId)) {
            throw new \RuntimeException("consumerId is required for 'remove-lock'");
        }

        $requiredParams = $this->processLockManager
            ->getConsumerLockIdParams($consumerId);

        if ($requiredParams && count($lockIdParams) != count($requiredParams)) {
            throw new \RuntimException("You must specify lock id params for " . join(', ', $requiredParams));
        }

        if ($force == false) {
            $confirmMsg = "<options=bold>Are you sure you want to remove this lock?</>"
                        . "\n<fg=red>WARNING: Removing a lock held by an active"
                        . " process can lead to overlapping processes.</>"
                        . "\n\n<options=bold>Really Remove Lock? Y/n</> ";

            $dialog     = $this->getHelperSet()->get('dialog');
            $continue   = $dialog->askConfirmation($output, "\n{$confirmMsg}");

            if ($continue == false) {
                $output->writeln("");
                $output->writeln("Lock *NOT* Removed");
                $output->writeln("");
                return;
            }
        }

        $lockIdParams = array_combine($requiredParams, $lockIdParams);
        $released = $this->processLockManager->removeLockForConsumer(
            $consumerId,
            $lockIdParams
        );

        if ($released == false) {
            $output->writeln("");
            $output->writeln("<fg=red>No Lock Found</>");
            $output->writeln("");
            return;
        }

        $output->writeln("");
        $output->writeln("<fg=green>Lock Removed</>");
        $output->writeln("");
    }
}
