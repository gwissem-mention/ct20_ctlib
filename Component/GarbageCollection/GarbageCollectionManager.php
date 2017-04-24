<?php
namespace CTLib\Component\GarbageCollection;

use CTLib\Component\Monolog\Logger;

/**
 * Manages garbage collection through registered garbage collectors.
 * @author Mike Turoff
 */
class GarbageCollectionManager
{

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var array
     * Set of garbage collectors.
     */
    protected $collectors;


    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->collectors = [];
    }

    /**
     * Adds garbage collector.
     * @param string $id
     * @param GarbageCollectorInterface $collector
     * @return void
     */
    public function addCollector($id, GarbageCollectorInterface $collector)
    {
        $this->collectors[$id] = $collector;
    }

    /**
     * Returns all garbage collectors.
     * @return array
     */
    public function getCollectors()
    {
        return $this->collectors;
    }

    /**
     * Returns specified garbage collector.
     * @param string $id
     * @return GarbageCollectorInterface|null
     */
    public function getCollector($id)
    {
        if (!isset($this->collectors[$id])) {
            return null;
        }

        return $this->collectors[$id];
    }

    /**
     * Instructs all garbage collectors to take out the trash.
     * @return array
     */
    public function collectAllGarbage()
    {
        $this->logger->debug("GarbageCollectionManager: collecting all garbage");

        $results = [];
        $gcDateCalculator = new GarbageCollectionDateCalculator();

        foreach ($this->collectors as $id => $collector) {
            $this->logger->debug("GarbageCollectionManager: telling collector '{$id}' to collect garbage");

            try {
                $purgeCount = $collector->collectGarbage($gcDateCalculator);
                $error = null;
            } catch (\Exception $e) {
                $purgeCount = null;
                $error = (string) $e;
            }

            $results[$id] = [
                'purgeCount' => $purgeCount,
                'error' => $error
            ];
        }

        return $results;
    }

}
