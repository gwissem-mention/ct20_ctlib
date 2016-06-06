<?php

namespace CTLib\Component\Cache;

/**
* Class used to manage all cached collections implementing
* CachedComponentInterface.
*
* @author David McLean <dmclean@celltrak.com>
*/
class CachedComponentManager
{
    /**
     * @var array
     */
    protected $cachedComponents = [];


    /**
    * Register a cached component with this mmanager.
    *
    * @param string $cachedComponentId
    * @param string $cachedComponentClass
    * @param CachedComponentInterface $cachedComponent
    */
    public function registerCachedComponent(
        $cachedComponentId,
        $cachedComponentClass,
        CachedComponentInterface $cachedComponent
    ) {
        $this->cachedComponents[$cachedComponentId] = [
            $cachedComponentClass,
            $cachedComponent
        ];
    }

    /**
    * Provides a listing of cached components in the forms
    * id -> classname (ex activity_types -> AppBundle\Helper\ActivityTypes)
    *
    * @return string
    */
    public function listCachedComponents()
    {
        $componentList = '';

        foreach ($this->cachedComponents as $id => list($className, $component)) {
                $componentList .=
                    str_pad($id, 36)
                    . ' -> '
                    . $className
                    . PHP_EOL;
        }
        return $componentList;
    }

    /**
    * Initialize cache for a single cached component or all registered
    * cached components.
    *
    * @param string $componentId
    */
    public function warmCache($componentId=null)
    {
        if ($componentId) {
            if (!isset($this->cachedComponents[$componentId])) {
                throw new \RuntimeException("{$componentId} not found");
            }
            list($class, $component) = $this->cachedComponents[$componentId];
            $component->warmCache();
        } else {
            foreach ($this->cachedComponents as $id => list($class, $component)) {
                $component->warmCache();
            }
        }
    }

    /**
    * Flush the cache for a single cached component or all registered
    * cached components.
    *
    * @param string $componentId
    */
    public function flushCache($componentId=null)
    {
        if ($componentId) {
            if (!isset($this->cachedComponents[$componentId])) {
                throw new \RuntimeException("{$componentId} not found");
            }
            list($class, $component) = $this->cachedComponents[$componentId];
            $component->flushCache();
        } else {
            foreach ($this->cachedComponents as $id => list($class, $component)) {
                $component->flushCache();
            }
        }
    }

    /**
    * Inspect the cache for a single cached component or all registered
    * cached components, by providing an array of the content.
    *
    * @param string $componentId
    *
    * @return array
    */
    public function inspectCache($componentId=null)
    {
        $cacheContent = [];

        if ($componentId) {
            if (!isset($this->cachedComponents[$componentId])) {
                throw new \RuntimeException("{$componentId} not found");
            }
            list($class, $component) = $this->cachedComponents[$componentId];
            $cacheContent = [
                'componentId'   => $componentId,
                'componentInfo' => $component->inspectCache()
            ];
        } else {
            foreach ($this->cachedComponents as $id => list($class, $component)) {
                $cacheContent[] = [
                    'componentId'   => $id,
                    'componentInfo' => $component->inspectCache()
                ];
            }
        }

        return $cacheContent;
    }
}
