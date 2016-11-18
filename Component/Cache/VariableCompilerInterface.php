<?php
namespace CTLib\Component\Cache;

/**
 * Interface for compiling variable value used in CompiledVariableCache.
 *
 * @author Mike Turoff
 */
interface VariableCompilerInterface
{

    /**
     * Compiles variable value from source files.
     * @param array $sourcePaths
     * @return mixed
     */
    public function compile(array $sourcePaths);

}
