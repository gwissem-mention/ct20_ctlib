<?php
namespace CTLib\Component\Monolog;

/**
 * Applies Runtime configuration values to log record.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class RuntimeProcessor
{
    
    /**
     * @var Runtime
     */
    protected $runtime;

    /**
     * @param AppKernel $kernel
     */
    public function __construct($kernel)
    {
        if (method_exists($kernel, 'getRuntime')) {
            $this->runtime = $kernel->getRuntime();
        } else {
            $this->runtime = false;
        }
    }

    /**
     * Applies runtime configuration values to log record.
     *
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        if (! $this->runtime) { return; }        

        $extra = array(
                    'environment'   => $this->runtime->getEnvironment(),
                    'exec_mode'     => $this->runtime->getExecMode(),
                    'brand_id'      => $this->runtime->getBrandId(),
                    'app_version'   => $this->runtime->getAppVersion() ?: '',
                    'app_platform'  => $this->runtime->getAppPlatform() ?: '',
                    'app_modules'   => join(',', $this->runtime->getAppModules()));

        if ($this->runtime->getSite()) {
            $extra['site_id']   = $this->runtime->getSite()->id;
            $extra['site_name'] = $this->runtime->getSite()->name;
        }

        $record['extra'] += $extra;
        return $record;
    }


}