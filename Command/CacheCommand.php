<?php

namespace CTLib\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CTLib\Component\Console\BaseCommand;


/**
 * Command to manage cached components.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class CacheCommand extends BaseCommand
{
    /**
     * @var CachedComponentManager
     */
    protected $cachedComponentManager;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setDescription('Manage cache')
             // Even though siteId is not used with this command, it is required
             // because appconsole requires it.
             ->addArgument('siteId', InputArgument::REQUIRED)
             ->addArgument('cacheManager', InputArgument::REQUIRED)
             ->addArgument('action', InputArgument::REQUIRED)
             ->addArgument('cachedComponent', InputArgument::OPTIONAL);
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->cachedComponentManager = $this->getService(
            'cache.manager.' . $input->getArgument('cacheManager')
        );

        $action = $input->getArgument('action');
        $cachedComponentId = $input->getArgument('cachedComponent');

        if (!$cachedComponentId
            && in_array($action, ['warmup-cache', 'flush-cache', 'inspect-cache'])) {
                throw new \RuntimeException("cachedComponent argument must be provided for '{$action}'");
        }

        switch ($action) {
            case 'warmup-cache':
                $this->execWarmupCachedComponent(
                    $cachedComponentId,
                    $input,
                    $output
                );
                break;
            case 'warmup-all-cache':
                $this->execWarmupCache($input, $output);
                break;
            case 'flush-cache':
                $this->execFlushCachedComponent(
                    $cachedComponentId,
                    $input,
                    $output
                );
                break;
            case 'flush-all-cache':
                $this->execFlushCache($input, $output);
                break;
            case 'inspect-cache':
                $this->execInspectCachedComponent(
                    $cachedComponentId,
                    $input,
                    $output
                );
                break;
            case 'inspect-all-cache':
                $this->execInspectCache($input, $output);
                break;
            case 'list-cached-components':
                $this->execListCachedComponents($input, $output);
                break;
            case 'migrate-cache':
                $this->execMigrateCache($input, $output);
                break;
            case 'list':
                $this->execActionList($input, $output);
                break;
            default:
                throw new \RuntimeException("Invalid action '{$action}'");
        }
    }

    /**
     * Formats action information.
     *
     * @param string $action
     * @param string $description
     *
     * @return string
     */
    protected function formatActionInfo($action, $description)
    {
        return str_pad($action, 30) . $description;
    }

    /**
     * Lists out possible command actions.
     *
     * @param  InputInterface $input
     * @param  OutputInterface $output
     *
     * @return void
     */
    protected function execActionList(
        InputInterface $input,
        OutputInterface $output
    ) {
        $dividerLength  = 90;

        $msg = "\n <bg=blue> Available Actions: </>"
             . "\n"

             . "\n" . str_repeat("-", $dividerLength)
             . "\n  " . $this->formatActionInfo("warmup-cache {component}", "Warm up the cache for a single given cached component")
             . "\n  " . $this->formatActionInfo("warmup-all-cache", "Warm up cache for all cached components")

             . "\n" . str_repeat("-", $dividerLength)
             . "\n  " . $this->formatActionInfo("flush-cache {component}", "Flush cache for a single given cached component")
             . "\n  " . $this->formatActionInfo("flush-all-cache", "Flush cache for all cached components")

             . "\n" . str_repeat("-", $dividerLength)
             . "\n  " . $this->formatActionInfo("inspect-cache {component}", "List details for cache for a single given cached component")
             . "\n  " . $this->formatActionInfo("inspect-all-cache", "List details of cache for all cached components")

             . "\n" . str_repeat("-", $dividerLength)
             . "\n  " . $this->formatActionInfo("list-cached-components", "Provides a list of all cached components")

             . "\n" . str_repeat("-", $dividerLength)
             . "\n  " . $this->formatActionInfo("list", "Lists these actions")
             . "\n" . str_repeat("-", $dividerLength)
             . "\n\n";

        $output->writeln($msg);
    }

    /**
     * Lists out registered cached components.
     *
     * @param  InputInterface $input
     * @param  OutputInterface $output
     *
     * @return void
     */
    protected function execListCachedComponents(
        InputInterface $input,
        OutputInterface $output
    ) {
        $dividerLength  = 90;

        $msg = "\n <bg=blue>Cached Services</>";
        $msg .= "\n" . str_repeat("-", $dividerLength) . "\n\n";
        $msg .= $this->cachedComponentManager->listCachedComponents();

        $output->writeln($msg);
    }

    /**
     * Warm up the cache for a single cached component.
     *
     * @param  string $cachedComponentId
     * @param  InputInterface $input
     * @param  OutputInterface $output
     *
     * @return void
     */
    protected function execWarmupCachedComponent(
        $cachedComponentId,
        InputInterface $input,
        OutputInterface $output
    ) {
        $output->writeln("");
        $output->writeln("Warming cache...");
        $output->writeln("");

        $this->cachedComponentManager->warmCache($cachedComponentId);

        $output->writeln("");
        $output->writeln("Cache is warm for '{$cachedComponentId}'");
        $output->writeln("");
    }

    /**
     * Warm up entire cache.
     *
     * @param  InputInterface $input
     * @param  OutputInterface $output
     *
     * @return void
     */
    protected function execWarmupCache(
        InputInterface $input,
        OutputInterface $output
    ) {
        $output->writeln("");
        $output->writeln("Warming cache...");
        $output->writeln("");

        $this->cachedComponentManager->warmCache();

        $output->writeln("");
        $output->writeln("Cache is warm!");
        $output->writeln("");
    }

    /**
     * Flush the cache for a single cached component.
     *
     * @param  string $cachedComponentId
     * @param  InputInterface $input
     * @param  OutputInterface $output
     *
     * @return void
     */
    protected function execFlushCachedComponent(
        $cachedComponentId,
        InputInterface $input,
        OutputInterface $output
    ) {
        $output->writeln("");
        $output->writeln("Flushing cache...");
        $output->writeln("");

        $this->cachedComponentManager->flushCache($cachedComponentId);

        $output->writeln("");
        $output->writeln("Cache flushed for '{$cachedComponentId}'");
        $output->writeln("");
    }

    /**
     * Flush entire cache.
     *
     * @param  InputInterface $input
     * @param  OutputInterface $output
     *
     * @return void
     */
    protected function execFlushCache(
        InputInterface $input,
        OutputInterface $output
    ) {
        $output->writeln("");
        $output->writeln("Flushing cache...");
        $output->writeln("");

        $this->cachedComponentManager->flushCache();

        $output->writeln("");
        $output->writeln("Cache flushed");
        $output->writeln("");
    }

    /**
     * Inspects cache of a single cached component.
     *
     * @param  string $cachedComponentId
     * @param  InputInterface $input
     * @param  OutputInterface $output
     *
     * @return void
     */
    protected function execInspectCachedComponent(
        $cachedComponentId,
        InputInterface $input,
        OutputInterface $output
    ) {
        $info = $this->cachedComponentManager->inspectCache($cachedComponentId);

        $output->writeln("");
        $output->writeln(str_repeat('-', 90));
        $output->writeln("Cached Service Info: ");
        $output->writeln(str_repeat('-', 90));
        $output->writeln("");
        $output->writeln("<bg=blue> {$info['componentId']} </>");
        $output->writeln($this->formatCacheInspectionInfo($info['componentInfo']));
        $output->writeln("");
    }

    /**
     * Inspect entire cache.
     *
     * @param  InputInterface $input
     * @param  OutputInterface $output
     *
     * @return void
     */
    protected function execInspectCache(
        InputInterface $input,
        OutputInterface $output
    ) {
        $components = $this->cachedComponentManager->inspectCache();

        $output->writeln("");
        $output->writeln(str_repeat('-', 90));
        $output->writeln(" Cached Services Info: ");
        $output->writeln(str_repeat('-', 90));

        foreach ($components as $component) {
            $output->writeln("");
            $output->writeln("<bg=blue> {$component['componentId']} </>");
            $output->writeln(
                $this->formatCacheInspectionInfo($component['componentInfo'])
            );
        }

        $output->writeln("");
    }

    /**
     * Migrates from memcache to redis.
     *
     * @param  InputInterface $input
     * @param  OutputInterface $output
     *
     * @return void
     */
    protected function execMigrateCache(
        InputInterface $input,
        OutputInterface $output
    ) {
        $output->writeln("");
        $output->writeln("Migrating Memcache to Redis...");
        $output->writeln("");

        $this->execFlushCache($input, $output);

        $this->execWarmupCache($input, $output);

        $output->writeln("");
        $output->writeln("Cache migration complete.");
        $output->writeln("");
    }

    /**
     * Helper method to format cache info returned from inspect.
     *
     * @param array $cacheInfo
     *
     * @return string
     */
    protected function formatCacheInspectionInfo(array $cacheInfo)
    {
        if (!isset($cacheInfo['content'])) {
            return 'NA';
        }

        $msg = "\n Content: \n"
             . $cacheInfo['content'];

        return $msg;
    }

    /**
     * Helper method to format cache info label for inspect.
     *
     * @param string $label
     *
     * @return string
     */
    protected function formatCacheInspectionLabel($label)
    {
        $labelPadLength = 40;
        $labelPadChar   = '.';
        $labelPadRepeat = $labelPadLength - strlen($label);

        return " {$label} "
                . str_repeat($labelPadChar, $labelPadRepeat);
    }
}
