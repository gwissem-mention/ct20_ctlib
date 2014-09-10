<?php
namespace CTLib\Util;

/**
 * Utility to sort collection based on item dependenencies on its peers.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class DependencySorter
{
    /**
     * @var callable
     */
    protected $getDependenciesCallback;

    /**
     * @var callable
     */
    protected $isDependencyCallback;

    
    /**
     * @param callable $getDependenciesCallback
     *                      array function($item)
     *                      Return set of item's dependency identifiers.
     *
     * @param callable $isDependencyCallback
     *                      boolean function($item, $dependency)
     *                      Returns whether $item matches $dependency.
     */
    public function __construct($getDependenciesCallback, $isDependencyCallback)
    {
        $this->getDependenciesCallback  = $getDependenciesCallback;
        $this->isDependencyCallback     = $isDependencyCallback;
    }

    /**
     * Sorts collection.
     *
     * @param array $collection
     *
     * @return array
     * @throws Exception    If dependency not found in collection.
     */
    public function sort(array $collection)
    {
        $sortedCollection = array();
        
        while ($collection) {
            $item               = array_shift($collection);
            $sortedCollection   = $this
                                    ->addItem(
                                        $item,
                                        $collection,
                                        $sortedCollection);
        }
        return $sortedCollection;
    }

    /**
     * Adds item to sorted collection.
     *
     * @param mixed $item
     * @param array &$collection
     * @param array $sortedCollection
     *
     * @return array
     */
    protected function addItem($item, &$collection, $sortedCollection)
    {
        $dependencies = call_user_func($this->getDependenciesCallback, $item);

        if (! $dependencies) {
            $sortedCollection[] = $item;
            return $sortedCollection;
        }

        $addAtIndex = 0;

        foreach ($dependencies as $dependency) {
            $index = $this->getDependencyIndex($sortedCollection, $dependency);

            if ($index === false) {
                // Haven't already added item to sorted collection. Need to pull
                // from original collection.
                $dependentItem = $this
                                    ->extractDependency($collection, $dependency);

                if (! $dependentItem) {
                    throw new \Exception("Dependency '{$dependency}' not found");
                }

                $sortedCollection = $this
                                    ->addItem(
                                        $dependentItem,
                                        $collection,
                                        $sortedCollection);
                $index = count($sortedCollection);
            } else {
                // Increment index so we add this item after found dependency.
                $index = $index + 1;
            }
            $addAtIndex = max($addAtIndex, $index);
        }
        array_splice($sortedCollection, $addAtIndex, 0, array($item));
        return $sortedCollection;
    }

    /**
     * Returns index of dependency within collection.
     *
     * @param array $collection
     * @param mixed $dependency
     *
     * @return integer|false    Returns false if dependency not found.
     */
    protected function getDependencyIndex($collection, $dependency)
    {
        foreach ($collection as $i => $item) {
            if (call_user_func($this->isDependencyCallback, $item, $dependency)) {
                return $i;
            }
        }
        return false;
    }

    /**
     * Extracts dependency item from collection.
     *
     * @param array &$collection
     * @param mixed $dependency
     * 
     * @return mixed    Returns null if dependency not found.
     */
    protected function extractDependency(&$collection, $dependency)
    {
        $index = $this->getDependencyIndex($collection, $dependency);

        if ($index === false) { return null; }

        $item = $collection[$index];
        unset($collection[$index]);
        $collection = array_values($collection);

        return $item;
    }


}