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

        $record['extra'] = array_merge(
            $record['extra'],
            array(
                'environment'   => $this->runtime->getEnvironment(),
                'exec_mode'     => $this->runtime->getExecMode(),
                'brand_id'      => $this->runtime->getBrandId(),
                'site_id'       => $this->runtime->getSiteId() ?: '',
                'app_version'   => $this->runtime->getAppVersion() ?: '',
                'app_platform'  => $this->runtime->getAppPlatform() ?: '',
                'app_modules'   => join(',', $this->runtime->getAppModules())
            )
        );
        return $record;
    }


}