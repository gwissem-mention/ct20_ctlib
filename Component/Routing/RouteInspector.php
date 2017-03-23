<?php
namespace CTLib\Component\Routing;

use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\Route;
use CTLib\Component\Monolog\Logger;

/**
 * Used to retrieve configured route properties.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class RouteInspector
{

    /**
     * Filename where the cached routes are stored.
     */
    const CACHE_FILENAME = 'routeinspectorcache.php';


    /**
     * @var Router
     */
    protected $router;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $cacheDir;

    /**
     * @var boolean
     */
    protected $isDebug;


    /**
     * @param Router $router
     * @param Logger $logger
     * @param string $cacheDir
     * @param boolean $isDebug
     */
    public function __construct(
        Router $router,
        Logger $logger,
        $cacheDir,
        $isDebug
    ) {
        $this->router   = $router;
        $this->logger   = $logger;
        $this->cacheDir = $cacheDir;
        $this->isDebug  = $isDebug;
        $this->routes   = [];
    }

    /**
     * Returns route's URL pattern.
     *
     * @param string $routeName
     * @return string
     * @throws Exception    If route not found for $routeName.
     */
    public function getPattern($routeName)
    {
        $route = $this->getRoute($routeName);
        return $route['pattern'];
    }

    /**
     * Returns route's URL parameters.
     *
     * @param string $routeName
     * @return array
     * @throws Exception    If route not found for $routeName.
     */
    public function getParameters($routeName)
    {
        $route = $this->getRoute($routeName);
        return $route['params'];
    }

    /**
     * Returns configure option for route.
     *
     * @param string $routeName
     * @param string $option
     *
     * @return mixed|null   Returns NULL if $option not defined.
     * @throws Exception    If route not found for $routeName.
     */
    public function getOption($routeName, $option)
    {
        $route = $this->getRoute($routeName);
        if (array_key_exists($option, $route['options']) == false) {
            return null;
        }
        return $route['options'][$option];
    }

    /**
     * Reloads routes fresh from the Router configuration.
     * @return void
     */
    public function reloadFreshRoutes()
    {
        $this->loadFreshRoutes();

        try {
            $this->flushToCache();
        } catch (\Exception $e) {
            $this->logger->error((string) $e);
        }
    }

    /**
     * Returns parsed route information.
     *
     * @param string $routeName
     * @return array
     * @throws Exception    If route not found for $routeName.
     */
    protected function getRoute($routeName)
    {
        if (empty($this->routes)) {
            $this->loadRoutes();
        }

        if (isset($this->routes[$routeName]) == false) {
            throw new \RuntimeException("Route not found for name '{$routeName}'");
        }

        return $this->routes[$routeName];
    }

    /**
     * Loads routes.
     * @return void
     */
    protected function loadRoutes()
    {
        if ($this->hasCache() == false
            || ($this->isDebug && $this->isCacheStale())) {
            $this->reloadFreshRoutes();
        } else {
            $this->loadCachedRoutes();
        }
    }

    /**
     * Loads routes fresh from the Router configuration.
     * @return void
     */
    protected function loadFreshRoutes()
    {
        $this->logger->debug("RouteInspector: loading fresh routes from Router");

        $this->routes = [];

        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            $this->routes[$name] = $this->parseRoute($route);
        }
    }

    /**
     * Loads routes from cache.
     * @return void
     */
    protected function loadCachedRoutes()
    {
        $this->logger->debug("RouteInspector: loading routes from cache");

        $cachePath = $this->getCacheFilePath();
        $this->routes = @include $cachePath;
    }

    /**
     * Flushes routes to cache.
     * @return void
     * @throws RuntimeException
     */
    protected function flushToCache()
    {
        $cachePath = $this->getCacheFilePath();

        $this->logger->debug("RouteInspector: flushing cache to '{$cachePath}'");

        $cacheDir = pathinfo($cachePath, PATHINFO_DIRNAME);

        if (@is_dir($cacheDir) == false) {
            $dirCreated = @mkdir($cacheDir, 0775, true);
            if ($dirCreated == false) {
                throw new \RuntimeException("Cannot create cache directory at '{$cacheDir}'");
            }
        }

        $contents = $this->formatCacheContents();
        $bytes = @file_put_contents($cachePath, $contents);

        if ($bytes === false) {
            throw new \RuntimeException("Cannot write route inspector cache to '{$cachePath}'");
        }
    }

    /**
     * Formats cache contents.
     * @return string
     */
    protected function formatCacheContents()
    {
        $className = get_class($this);
        $routesStr = var_export($this->routes, true);
        $contents  = "<?php
// routeinspectorcache.php
// This file is generated automatically by {$className}.
// ** DO NOT MODIFY **

// Return the parsed routes used by the RouteInspector.
return {$routesStr};";

        return $contents;
    }

    /**
     * Indicates whether cache exists.
     * @return boolean
     */
    protected function hasCache()
    {
        $cachePath = $this->getCacheFilePath();
        return @file_exists($cachePath);
    }

    /**
     * Indicates whether cache is stale.
     * @return boolean
     */
    protected function isCacheStale()
    {
        $this->logger->debug("RouteInspector: checking whether cache is stale");

        $cachePath = $this->getCacheFilePath();
        $cacheTime = @filemtime($cachePath);

        if ($cacheTime == false) {
            $this->logger->debug("RouteInspector: cache is stale b/c failed getting mtime for '{$cachePath}'");
            return true;
        }

        $routeResources = $this->router->getRouteCollection()->getResources();

        foreach ($routeResources as $routeResource) {
            $resourcePath = $routeResource->getResource();
            $resourceTime = @filemtime($resourcePath);

            if ($resourceTime >= $cacheTime) {
                $this->logger->debug("RouteInspector: cache is stale b/c '{$resourcePath}' has been modified");
                return true;
            }
        }

        $this->logger->debug("RouteInspector: cache is not stale");
        return false;
    }

    /**
     * Returns path to cache file.
     * @return string
     */
    protected function getCacheFilePath()
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . self::CACHE_FILENAME;
    }

    /**
     * Parses Symfony Route into format used by RouteInspector.
     *
     * @param Route $route
     * @return array
     */
    protected function parseRoute(Route $route)
    {
        return [
            'pattern'   => $route->getPattern(),
            'params'    => $this->parseRouteParams($route),
            'options'   => $this->parseRouteOptions($route)
        ];
    }

    /**
     * Parses route's URL parameters.
     *
     * @param Route $route
     * @return array
     */
    protected function parseRouteParams(Route $route)
    {
        $search = '/{([a-z_0-9]+)}/i';
        $routePattern = $route->getPattern();
        $count  = preg_match_all($search, $routePattern, $matches);
        return $count ? $matches[1] : [];
    }

    /**
     * Parses route's configuration options.
     *
     * @param Route $route
     * @return array
     */
    protected function parseRouteOptions(Route $route)
    {
        $skipOptions = ['compiler_class'];
        $options = [];
        foreach ($route->getOptions() as $option => $value) {
            if (in_array($option, $skipOptions) == false) {
                $options[$option] = $value;
            }
        }
        return $options;
    }

}
