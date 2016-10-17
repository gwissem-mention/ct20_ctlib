<?php

namespace CTLib\Component\FilteredObjectIndex;

/**
* Interface to be implemented by entity filter compiler classes.
*
* @author David McLean <dmclean@celltrak.com>
*/
interface EntityFilterCompiler
{
    /**
     * Determines if the compiler supports compiling
     * filters for the given entity.
     */
    function supportsEntity();

    /**
     * Retrieve and collect all filters associated
     * to an entity.
     */
    function compileFilters();
}
