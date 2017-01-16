<?php
namespace CTLib\Component\Cache;

/**
 * Manages caching a compiled variable into a PHP cache file.
 *
 * @author Mike Turoff
 */
class CompiledVariableCache
{
    /**
     * Object that compiles variable value.
     * @var VariableCompilerInterface
     */
    protected $compiler;

    /**
     * Set of path files that contain the uncompiled variable value.
     * @var array
     */
    protected $sourcePaths;

    /**
     * Path to variable cache file.
     * @var string
     */
    protected $cachePath;

    /**
     * Indicates whether to check file modtimes to determine whether cache is
     * stale.
     * @var boolean
     */
    protected $checkFileModifiedTimes;


    /**
     * @param VariableCompilerInterface $compiler
     * @param array $sourcePaths
     * @param string $cachePath
     * @param boolean $checkFileModifiedTimes
     */
    public function __construct(
        VariableCompilerInterface $compiler,
        array $sourcePaths,
        $cachePath,
        $checkFileModifiedTimes = false
    ) {
        $this->compiler = $compiler;
        $this->sourcePaths = $sourcePaths;
        $this->cachePath = $cachePath;
        $this->checkFileModifiedTimes = $checkFileModifiedTimes;
    }

    /**
     * Returns the variable's value.
     * @return mixed
     */
    public function getVariable()
    {
        if ($this->isCacheStale()) {
            $var = $this->loadVariableFromSource();
            $this->cacheVariable($var);
        } else {
            $var = $this->loadVariableFromCache();
        }
        return $var;
    }

    /**
     * Indicates whether cache exists for this variable.
     * @return boolean
     */
    public function hasCache()
    {
        return is_readable($this->cachePath);
    }

    /**
     * Indicates whether the cache is stale (including if it doesn't exist.)
     * @return boolean
     */
    public function isCacheStale()
    {
        if (!$this->hasCache()) {
            return true;
        }

        if (!$this->checkFileModifiedTimes) {
            return false;
        }

        $cacheTime = $this->getCacheModifiedTime();

        if (!$cacheTime) {
            return true;
        }

        $sourceTime = $this->getSourceModifiedTime();

        return $cacheTime < $sourceTime;
    }

    /**
     * Returns modified time of cache file.
     * @return integer
     */
    protected function getCacheModifiedTime()
    {
        return @filemtime($this->cachePath);
    }

    /**
     * Returns latest modified time of source files.
     * @return integer
     */
    protected function getSourceModifiedTime()
    {
        $sourceTime = 0;

        foreach ($this->sourcePaths as $sourcePath) {
            $modifiedTime = @filemtime($sourcePath);

            if (!$modifiedTime) {
                throw new \RuntimeException("Source '{$sourcePath}' does not exist");
            }

            $sourceTime = max($sourceTime, $modifiedTime);
        }

        return $sourceTime;
    }

    /**
     * Loads variable from source.
     * @return mixed
     */
    protected function loadVariableFromSource()
    {
        return $this->compiler->compile($this->sourcePaths);
    }

    /**
     * Loads variable from cache.
     * @return mixed
     */
    protected function loadVariableFromCache()
    {
        return @include $this->cachePath;
    }

    /**
     * Caches variable value into PHP file.
     * @param mixed $var
     * @return boolean  Indicates whether successfully cached.
     */
    protected function cacheVariable($var)
    {
        $contents =
            "<?php" .
            "\n// This file is created automatically by CTLib\Cache\CompiledVariableCache" .
            "\n// using " . get_class($this->compiler) .
            "\n// ** DO NOT EDIT. **" .
            "\n\nreturn " . var_export($var, true) . ";";

        $bytes = @file_put_contents($this->cachePath, $contents);
        return $bytes > 0 ? true : false;
    }


}
