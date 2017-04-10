<?php
namespace CTLib\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CTLib\Component\Console\BaseCommand;


class ProcessLockCommand extends BaseCommand
{
    public function configure()
    {
        parent::configure();

        $this->setDescription('Manages process locks')
            ->addArgument('action', InputArgument::REQUIRED, "Use 'list' to see available actions")
            ->addArgument('consumerId', InputArgument::OPTIONAL, 'Service ID of process lock consumer')
            ->addOption('lockIdParam', 'P', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'Sequenced lock id parameter');
    }


    public function execListConsumers(
        InputInterface $input,
        OutputInterface $output
    ) {
        $consumers = $this->processLockManager->getConsumers();

        
    }

    public function execShowLocksForConsumer(
        InputInterface $input,
        OutputInterface $output
    ) {
        $consumerId = $input->getArgument('consumerId');

        if (empty($consumerId)) {
            throw new \RuntimeException("consumerId is required for 'show-locks'");
        }

        $locks = $this->processLockManager->findLocksForConsumer($consumerId);

    }

    public function execFindLockForConsumer(
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
            throw new \RuntimException("You must specify lock id params for " . join(', ', $requiredParams));
        }

        $lockIdParams = array_combine($requiredParams, $lockIdParams);
        $lock = $this->processLockManager->findLockForConsumer(
            $consumerId,
            $lockIdParams
        );


    }

    public function execReleaseLockForConsumer(InputInterface $input, OutputInterface $output)
    {
        $consumerId = $input->getArgument('consumerId');
        $lockIdParams = $input->getOption('lockIdParam') ?: [];

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
