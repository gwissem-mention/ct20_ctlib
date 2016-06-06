<?php

namespace CTLib\Component\Cache;

/**
* Interface to be implemented by cached collection classes.
*
* @author David McLean <dmclean@celltrak.com>
*/
interface CachedComponentInterface
{
    /**
     * Initialize cache with data.
     */
    function warmCache();

    /**
     * Clear all cached data.
     */
    function flushCache();

    /**
    * Inspect the contents of the cache.
    *
    * @return array
    */
    function inspectCache();

    /**
     * Get a description of the cached component.
     *
     * @return string
     */
    function getCacheDescription();
}
