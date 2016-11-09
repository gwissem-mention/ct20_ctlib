<?php
namespace CTLib\Component\Cache;


class CompiledVariableCache
{

    public function __construct(
        VariableCompilerInterface $compiler,
        array $sourcePaths,
        $cachePath,
        $checkCacheTime = false
    ) {
        $this->compiler = $compiler;
        $this->sourcePaths = $sourcePaths;
        $this->cachePath = $cachePath;
        $this->checkCacheTime = $checkCacheTime;
    }

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

    public function hasCache()
    {
        return is_readable($this->cachePath);
    }

    public function isCacheStale()
    {
        if (!$this->hasCache()) {
            return true;
        }

        if (!$this->checkCacheTime) {
            return false;
        }

        $cacheTime = $this->getCacheModifiedTime();

        if (!$cacheTime) {
            return true;
        }

        $sourceTime = $this->getSourceModifiedTime();

        return $cacheTime < $sourceTime;
    }

    protected function getCacheModifiedTime()
    {
        return @filemtime($this->cachePath);
    }

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

    protected function loadVariableFromSource()
    {
        return $this->compiler->compile($this->sourcePaths);
    }

    protected function loadVariableFromCache()
    {
        return @include $this->cachePath;
    }

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
