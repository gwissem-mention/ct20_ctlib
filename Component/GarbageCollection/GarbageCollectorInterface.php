<?php
namespace CTLib\Component\GarbageCollection;

/**
 * Defines Garbage Collector.
 * @author Mike Turoff
 */
interface GarbageCollectorInterface
{

    /**
     * Takes out the trash.
     * @param GarbageCollectionDateCalculator $calculator
     * @return integer  Number of items purged.
     */
    public function collectGarbage(GarbageCollectionDateCalculator $calculator);

}
