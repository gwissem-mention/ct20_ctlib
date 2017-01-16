<?php

namespace CTLib\Component\EntityFilterCompiler;

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
     *
     * @param $entity
     *
     * @return bool
     */
    function supportsEntity($entity);

    /**
     * Retrieve and collect all filters associated
     * to an entity.
     *
     * @param $entity
     *
     * @return array
     */
    function compileFilters($entity);
}
