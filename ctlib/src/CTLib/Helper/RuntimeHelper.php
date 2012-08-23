<?php
namespace CTLib\Helper;

use CTLib\Util\Arr;


class RuntimeHelper
{
    const EXEC_MODE_STANDARD    = 'std';
    const EXEC_MODE_SERVICE     = 'svc';
    const EXEC_MODE_CLI         = 'cli';


    protected $environment;
    protected $debugEnabled;
    protected $serviceMode;
    protected $brandId;
    protected $brandName;
    protected $siteId;
    protected $appVersion;
    protected $site;

    public function __construct($config=array(), $site=null)
    {
        $this->environment  = $this->load('environment', $config);
        $this->debugEnabled = (bool) $this->load('debug', $config);
        $this->serviceMode  = (bool) $this->load('service', $config);
        $this->siteId       = $this->load('siteId', $config);
        $this->brandId      = $this->load('brandId', $config);
        $this->brandName    = $this->load('brandName', $config);
        $this->appVersion   = $this->load('appVersion', $config);    
        $this->site         = $site;
    }

    /**
     * Loads attribute from either $config or HTTP environment variable.
     *
     * @param string $key
     * @param array $config
     *
     * @return mixed
     */
    protected function load($key, $config)
    {
        return Arr::get($key, $config, $this->fromEnv($key));
    }

    /**
     * Returns HTTP environment variable for $key.
     *
     * @param string $key
     * @return mixed
     */
    protected function fromEnv($key)
    {
        //if ($this->isCliMode()) { return null; }

        $key = preg_replace('/([a-z])([A-Z])/', '$1_$2', $key);
        $key = strtoupper($key);
        return Arr::get("SYMFONY__CT__{$key}", $_SERVER);
    }

    /**
     * Returns $environment ('dev', 'test', 'production', etc.).
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Indicates whether debug mode is enabled.
     * @return boolean
     */
    public function isDebugEnabled()
    {
        return $this->debugEnabled;
    }

    /**
     * Returns the exec mode (standard, service or command-line).
     * @return string   Returns RuntimeHelper::EXEC_MODE* constant.
     */
    public function getExecMode()
    {
        if ($this->isCliMode()) {
            return self::EXEC_MODE_CLI;    
        } elseif ($this->isServiceMode()) {
            return self::EXEC_MODE_SERVICE;
        } else {
            return self::EXEC_MODE_STANDARD;
        }
    }

    /**
     * Indicates whether executing as web service.
     * @return boolean
     */
    public function isServiceMode()
    {
        return $this->serviceMode;
    }

    /**
     * Indicates whether executing via command-line.
     * @return boolean
     */
    public function isCliMode()
    {
        return PHP_SAPI == 'cli';
    }

    /**
     * Returns $brandId.
     * @return string
     */
    public function getBrandId()
    {
        return $this->brandId;
    }

    /**
     * Returns $brandName.
     * @return string
     */
    public function getBrandName()
    {
        return $this->brandName;
    }

    /**
     * Returns $siteId.
     * @return string
     */
    public function getSiteId()
    {
        return $this->siteId;
    }

    /**
     * Returns $appVersion.
     * @return string
     */
    public function getAppVersion()
    {
        return $this->appVersion;
    }

    /**
     * Indicates whether executing in dev environment.
     * @return boolean
     */
    public function isDev()
    {
        return $this->getEnvironment() == 'dev';
    }

    /**
     * Indicates whether runtime is configured to load AppBundle code rather
     * than the default GatewayBundle.
     * @return boolean
     */
    public function isReadyForApp()
    {
        return $this->getSiteId() && $this->getAppVersion();
    }

    /**
     * Returns runtime's cache/log directory.
     *
     * @return string
     * @throws Exception
     */
    public function getDir()
    {
        if ($this->isReadyForApp()) {
            return $this->getAppVersion() . '/' .
                $this->getSiteId() . '/' .
                $this->getExecMode();
        } elseif ($this->getBrandId()) {
            return $this->getBrandId() . '/' . $this->getExecMode();
        } else {
            throw new \Exception("Cannot determine directory.");
        }
    }

    /**
     * Returns runtime's directory for app-scoped assets.
     *
     * @return string
     * @throws Exception
     */
    public function getAppAssetDir()
    {
        if (! $this->getBrandId()) {
            throw new \Exception("brandId not set");
        }
        if (! $this->getAppVersion()) {
            throw new \Exception("appVersion not set");
        }
        return $this->getBrandId() . '/' . $this->getAppVersion();
    }

    /**
     * Returns runtime's directory for brand-scoped assets.
     *
     * @return string
     * @throws Exception
     */
    public function getBrandAssetDir()
    {
        if (! $this->getBrandId()) {
            throw new \Exception("brandId not set");
        }
        return $this->getBrandId();
    }

    /**
     * Returns site object injected in webservice/app.php.
     *
     * @return StdClass
     */
    public function getSite()
    {
        return $this->site;
    }
    
    /**
     * Shortcut to initializing new RuntimeHelper from site object created in
     * webservice/app.php and appconsole.
     *
     * @param StdClass $site
     * @param array $config     Additional configuration to pass to runtime.
     *
     * @return RuntimeHelper
     */
    public static function createFromSite($site, $config=array())
    {
        $config = array_merge($config, array(
            'siteId'        => $site->id,
            'brandId'       => $site->brandId,
            'brandName'     => $site->brandName,
            'appVersion'    => $site->appVersion
        ));
        return new self($config, $site);
    }
}

