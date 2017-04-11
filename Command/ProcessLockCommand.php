<?php
namespace CTLib\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CTLib\Component\Console\BaseCommand;
use CTLib\Component\Console\ConsoleTable;
use CTLib\Component\Console\ConsoleOutputHelper;


class ProcessLockCommand extends BaseCommand
{
    public function configure()
    {
        parent::configure();

        $this->setDescription('Manages process locks')
            ->addArgument('action', InputArgument::REQUIRED, "Use 'list' to see available actions")
            ->addArgument('consumerId', InputArgument::OPTIONAL, 'Service ID of process lock consumer')
            ->addOption('lockIdParam', 'P', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'Sequenced lock id parameter')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompts');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init();

        $action = $input->getArgument('action');

        switch ($action) {
            case 'list':
                return $this->execList($input, $output);

            case 'list-consumers':
                return $this->execListConsumers($input, $output);

            case 'show-locks':
                return $this->execShowLocksForConsumer($input, $output);

            case 'find-lock':
                return $this->execFindLockForConsumer($input, $output);

            case 'release-lock':
                return $this->execReleaseLockForConsumer($input, $output);

            default:
                throw new \RuntimeException("Invalid action '{$action}'. Use 'list' to see available actions.");
        }

    }

    protected function init()
    {
        $container = $this->getContainer();

        $this->processLockManager = $container->get('process_lock.manager');
    }

    protected function execList(InputInterface $input, OutputInterface $output)
    {
        $controlActions = [
            'show-locks {consumerId}' => 'Shows existing locks for consumer',
            'find-lock {consumerId}' => 'Finds specific lock for consumer',
            'release-lock {consumerId}' => 'Releases specific lock for consumer'
        ];

        $helpActions = [
            'list-consumers' => 'Lists registered consumers',
            'list' => 'Lists these actions'
        ];

        $outputHelper = new ConsoleOutputHelper($output);
        $outputHelper->outputActionList($controlActions, $helpActions);
    }

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
            ->addColumn('Name', 35)
            ->addColumn('Lock ID Pattern', 50)
            ->addColumn('Lock TTL', 9);

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

    protected function execShowLocksForConsumer(
        InputInterface $input,
        OutputInterface $output
    ) {
        $consumerId = $input->getArgument('consumerId');

        if (empty($consumerId)) {
            throw new \RuntimeException("consumerId is required for 'show-locks'");
        }

        $locks = $this->processLockManager->findLocksForConsumer($consumerId);

        if (empty($locks)) {
            $output->writeln("");
            $output->writeln("<fg=red>No locks found</>");
            $output->writeln("");
            return;
        }

        $table = new ConsoleTable;
        $table
            ->addColumn('Lock', 50)
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

    protected function execFindLockForConsumer(
        InputInterface $input,
        OutputInterface $output
    ) {
        $consumerId = $input->getArgument('consumerId');
        $lockIdParams = $input->getOption('lockIdParam') ?: [];

        if (empty($consumerId)) {
            throw new \RuntimeException("consumerId is required for 'show-locks'");
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
            $output->writeln("<fg=red>No lock found</>");
            $output->writeln("");
            return;
        }

        $output->writeln("");
        $outputHelper = new ConsoleOutputHelper($output);
        $outputHelper->outputAttributeValuePair('Lock', $lock['key']);
        $outputHelper->outputAttributeValuePair('TTL', $lock['ttl']);
        $output->writeln("");
    }

    protected function execReleaseLockForConsumer(InputInterface $input, OutputInterface $output)
    {
        $consumerId     = $input->getArgument('consumerId');
        $lockIdParams   = $input->getOption('lockIdParam') ?: [];
        $force          = $input->getOption('force');

        if (empty($consumerId)) {
            throw new \RuntimeException("consumerId is required for 'show-locks'");
        }

        $requiredParams = $this->processLockManager
            ->getConsumerLockIdParams($consumerId);

        if ($requiredParams && count($lockIdParams) != count($requiredParams)) {
            throw new \RuntimException("You must specify lock id params for " . join(', ', $requiredParams));
        }

        $lockIdParams = array_combine($requiredParams, $lockIdParams);
        $released = $this->processLockManager->releaseLockForConsumer(
            $consumerId,
            $lockIdParams
        );


    }
}
