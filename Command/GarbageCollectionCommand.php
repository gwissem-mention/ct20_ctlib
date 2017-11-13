<?php

namespace CTLib\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CTLib\Component\Console\BaseCommand;


/**
 * Command to Clean up Garbage.
 *
 * @author Zachary Scally <zscally@celltrak.com>
 */
class GarbageCollectionCommand extends BaseCommand
{
    /**
     * @var garbageCollectionManager
     */
    protected $garbageCollectionManager;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setDescription('Garbage Collection');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->debug("GarbageCollectorListener: START");
        $this->garbageCollectionManager = $this->getService('garbageCollectionManager');
        $this->garbageCollectionManager->collectAllGarbage();
        $this->logger->debug("GarbageCollectorListener: END");
    }
}
