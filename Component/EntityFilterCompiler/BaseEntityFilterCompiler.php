<?php

namespace CTLib\Component\EntityFilterCompiler;

use Doctrine\Common\Util\ClassUtils;

/**
 * Abstract compiler class used by all other entity filter compilers.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
abstract class BaseEntityFilterCompiler implements EntityFilterCompiler
{
    /**
     * Name of class representing the type of entity
     * the fitler compiler supports.
     *
     * @var string
     */
    private $supportedClassName;

    /**
     * @inherit
     */
    abstract public function compileFilters($entity);

    /**
     * @param string $className
     *
     * @return void
     */
    public function setSupportedClassName($className)
    {
        $this->supportedClassName = $className;
    }

    /**
     * @return string
     */
    public function getSupportedClassName()
    {
        return $this->supportedClassName;
    }

    /**
     * Determines if the compiler supports compiling
     * filters for the given entity.
     *
     * @param $entity
     *
     * @return bool
     */
    public function supportsEntity($entity)
    {
        // Use ClassUtils to get proper class name
        // as passed $entity could be a Proxy class of the real entity
        if (ClassUtils::getClass($entity) !== $this->supportedClassName) {
            return false;
        }
        return true;
    }
}
