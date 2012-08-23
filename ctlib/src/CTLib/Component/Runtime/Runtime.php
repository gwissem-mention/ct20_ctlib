<?php
namespace CTLib\Component\Runtime;

use CTLib\Util\Arr;

/**
 * Defines standard Symfony configs environment and debug mode along with
 * custom settings for brand, site and app version.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class Runtime
{
    const ENVIRONMENT_DEVELOPMENT   = 'dev';
    const ENVIRONMENT_UNITTEST      = 'unit';
    const ENVIRONMENT_QA            = 'qa';
    const ENVIRONMENT_STAGING       = 'stg';
    const ENVIRONMENT_PRODUCTION    = 'prod';

    const EXEC_MODE_STANDARD        = 'std';
    const EXEC_MODE_SERVICE         = 'svc';
    const EXEC_MODE_CONSOLE         = 'cli';

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var boolean
     */
    protected $debugEnabled;

    /**
     * @var string
     */
    protected $execMode;

    /**
     * @var string
     */
    protected $brandId;

    /**
     * @var string
     */
    protected $brandName;

    /**
     * @var stdClass
     */
    protected $site;

    /**
     * @var string
     */
    protected $userAppVersion;


    /**
     * @param string $environment       dev, qa, prod, etc.
     * @param boolean $debugEnabled
     * @param string $execMode
     * @param string $brandId
     * @param string $brandName
     * @param stdClass $site
     * @param string $userAppVersion
     */
    public function __construct($environment, $debugEnabled, $execMode, $brandId,
        $brandName, $site=null, $userAppVersion=null)
    {
        if (! self::isValidExecMode($execMode)) {
            throw new \Exception("Invalid execMode: {$execMode}");
        }
        $this->environment      = $environment;
        $this->debugEnabled     = (bool) $debugEnabled;
        $this->execMode         = $execMode;
        $this->brandId          = $brandId;
        $this->brandName        = $brandName;
        $this->site             = $site;
        $this->userAppVersion   = $userAppVersion;
    }


    /**
     * Returns environment.
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Indicates if development environment.
     * @return boolean
     */
    public function isDevelopment()
    {
        return $this->getEnvironment() == self::ENVIRONMENT_DEVELOPMENT;
    }

    /**
     * Indicates if qa environment.
     * @return boolean
     */
    public function isQA()
    {
        return $this->getEnvironment() == self::ENVIRONMENT_QA;
    }

    /**
     * Indicates if staging environment.
     * @return boolean
     */
    public function isStaging()
    {
        return $this->getEnvironment() == self::ENVIRONMENT_STAGING;
    }

    /**
     * Indicates if production environment.
     * @return boolean
     */
    public function isProduction()
    {
        return $this->getEnvironment() == self::ENVIRONMENT_PRODUCTION;
    }

    /**
     * Indicates if test environment.
     *
     * @return boolean
     */
    public function isUnitTest()
    {
        return $this->getEnvironment() == self::ENVIRONMENT_UNITTEST;
    }

    /**
     * Indicates if debug is enabled.
     * @return boolean
     */
    public function isDebugEnabled()
    {
        return $this->debugEnabled;
    }

    /**
     * Returns execMode.
     * @return string
     */
    public function getExecMode()
    {
        return $this->execMode;
    }

    /**
     * Indicates if standard exec mode.
     * @return boolean
     */
    public function isStandardExecMode()
    {
        return $this->getExecMode() == self::EXEC_MODE_STANDARD;
    }

    /**
     * Indicates if service exec mode.
     * @return boolean
     */
    public function isServiceExecMode()
    {
        return $this->getExecMode() == self::EXEC_MODE_SERVICE;
    }

    /**
     * Indicates if console exec mode.
     * @return boolean
     */
    public function isConsoleExecMode()
    {
        return $this->getExecMode() == self::EXEC_MODE_CONSOLE;
    }

    /**
     * Returns brandId.
     * @return string
     */
    public function getBrandId()
    {
        return $this->brandId;
    }

    /**
     * Returns brandName
     * @return string
     */
    public function getBrandName()
    {
        return $this->brandName;
    }

    /**
     * Returns site.
     * @return stdClass|null
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * Returns siteId.
     * @return string|null  Returns null if site not set.
     */
    public function getSiteId()
    {
        return $this->getSiteValue('id');
    }

    /**
     * Returns siteName.
     * @return string|null  Returns null if site not set.
     */
    public function getSiteName()
    {
        return $this->getSiteValue('name');
    }

    /**
     * Returns siteServiceAuth.
     * @return string|null  Returns null if site not set.
     */
    public function getSiteServiceAuth()
    {
        return $this->getSiteValue('serviceAuth');
    }

    /**
     * Returns siteInterfaceAuth.
     * @return string|null  Returns null if site not set.
     */
    public function getSiteInterfaceAuth()
    {
        return $this->getSiteValue('interfaceAuth');
    }

    /**
     * Returns usable locales.
     * @return array
     */
    public function getLocales()
    {
        return $this->getSiteValue('locales') ?: array();
    }

    /**
     * Returns appVersion.
     * @return string|null  Returns null if appVersion not set.
     */
    public function getAppVersion()
    {
        if (! $this->site) { return null; }
        return $this->userAppVersion ?: $this->getSiteValue('appVersion');
    }

    /**
     * Returns app platform.
     * @return string|null  Returns null if site not set.
     */
    public function getAppPlatform()
    {
        return $this->getSiteValue('appPlatform');
    }

    /**
     * Returns enabled app modules.
     * @return array
     */
    public function getAppModules()
    {
        return $this->getSiteValue('appModules') ?: array();
    }

    /**
     * Indicates whether $module is enabled.
     *
     * @param string $module
     * @return boolean
     */
    public function hasModule($module)
    {
        return in_array($module, $this->getAppModules());
    }

    /**
     * Returns user's app version.
     * @return string|null
     */
    public function getUserAppVersion()
    {
        return $this->userAppVersion;
    }

    /**
     * Indicates whether runtime is configured for AppBundle.
     *
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
    public function getDir($withExecMode=true)
    {
        if ($this->isReadyForApp()) {
            $dir = $this->getAppVersion() . '/' . $this->getSiteId();
            if ($withExecMode) {
                $dir .= '/' . $this->getExecMode();
            }
            return $dir;
        } elseif ($this->getBrandId()) {
            $dir = $this->getBrandId();
            if ($withExecMode) {
                $dir .= '/' . $this->getExecMode();
            }
            return $dir;
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
     * Returns value for site $property.
     *
     * @param string $property
     *
     * @return mixed    Returns null if site not set.
     * @throws Exception If site set but doesn't have $property defined.s
     */
    protected function getSiteValue($property)
    {
        if (! $this->site) { return null; }
        if (! isset($this->site->{$property})) {
            throw new \Exception("Invalid site property: {$property}");
        }
        return $this->site->{$property};
    }

    /**
     * Indicated whether $execMode is valid.
     *
     * @param string $execMode
     * @return boolean
     */
    public static function isValidExecMode($execMode)
    {
        return in_array($execMode, array(
            self::EXEC_MODE_STANDARD,
            self::EXEC_MODE_SERVICE,
            self::EXEC_MODE_CONSOLE));
    }

    /**
     * Retrieves value for server $property.
     *
     * @param string $property
     *
     * @return mixed
     * @throws Exception    If $property not defined in server config.
     */
    public static function getServerValue($property)
    {
        return Arr::mustGet("SYMFONY__CT__{$property}", $_SERVER);
    }

    /**
     * Creates new Runtime for Gateway in standard exec mode.
     *
     * @return Runtime
     */
    public static function createForGateway()
    {
        return new self(
            self::getServerValue('ENVIRONMENT'),
            self::getServerValue('DEBUG'),
            self::EXEC_MODE_STANDARD,
            self::getServerValue('BRAND_ID'),
            self::getServerValue('BRAND_NAME')
        );
    }

    /**
     * Creates new Runtime for App in standard exec mode.
     *
     * @return Runtime
     */
    public static function createForApp($site, $userAppVersion=null)
    {
        return new self(
            self::getServerValue('ENVIRONMENT'),
            self::getServerValue('DEBUG'),
            self::EXEC_MODE_STANDARD,
            $site->brandId,
            $site->brandName,
            $site
        );
    }

    /**
     * Creates new Runtime for App in service exec mode.
     *
     * @return Runtime
     */
    public static function createForAppService($site)
    {
        return new self(
            self::getServerValue('ENVIRONMENT'),
            self::getServerValue('DEBUG'),
            self::EXEC_MODE_SERVICE,
            $site->brandId,
            $site->brandName,
            $site
        );
    }

    /**
     * Creates new Runtime for App in console exec mode.
     *
     * @return Runtime
     */
    public static function createForAppConsole($site, $environment,
        $debugEnabled)
    {
        return new self(
            $environment,
            $debugEnabled,
            self::EXEC_MODE_CONSOLE,
            $site->brandId,
            $site->brandName,
            $site
        );
    }
}
