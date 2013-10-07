<?php
namespace CTLib\Component\Routing;

/**
 * Used to retrieve configured route properties.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class RouteInspector
{
    /**
     * @var Router
     */
    protected $router;

    /**
     * @var SharedCacheHelper
     */
    protected $cache;

    /**
     * @var string
     */
    protected $cacheNamespace;

    
    /**
     * @param Router $router
     * @param SharedCacheHelper $cache
     * @param string $cacheNamespace    Will be used to segregate cached route
     *                                  configuration from another.
     */
    public function __construct($router, $cache, $cacheNamespace)
    {
        $this->router           = $router;
        $this->cache            = $cache;
        $this->cacheNamespace   = $cacheNamespace;
        $this->routes           = array();
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
        if (! array_key_exists($option, $route['options'])) {
            return null;
        }
        return $route['options'][$option];
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
        if ($this->cache->isEnabled()) {
            // Use the preferred method of retrieving all routes from cache.
            // This is the high performance method.
            if (! $this->routes) {
                $this->routes = $this->loadRoutes();
            }
        } elseif (! isset($this->routes[$routeName])) {
            // Without acccess to the cache and if this route isn't already in
            // memory, then it's best to just get this one route's information
            // rather than the entire collection. Typically, each request will
            // only need to inspect one or two routes, and this will offer
            // better performance than parsing all of them. 
            $route = $this->router->getRouteCollection()->get($routeName);
            if ($route) {
                $this->routes[$routeName] = $this->parseRoute($route);
            }
        } else;

        if (! isset($this->routes[$routeName])) {
            throw new \Exception("Route not found for name '{$routeName}'");
        }
        return $this->routes[$routeName];
    }

    /**
     * Loads route information from Symfony Router, parses and stores in cache.
     *
     * @return array    Enumerated array of parsed routes.
     */
    protected function loadRoutes()
    {
        $cacheKey   = $this->getCacheKey();
        $routes     = $this->cache->get($cacheKey);

        if ($routes) {
            // Found them in the cache!
            return $routes;
        }
        // Need to reload fresh from Symfony router. This requires YAML parsing
        // so hopefully it won't happen very often.
        $routes = array();
        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            $routes[$name] = $this->parseRoute($route);
        }
        $this->cache->set($cacheKey, $routes);
        return $routes;
    }

    /**
     * Parses Symfony Route into format used by RouteInspector.
     *
     * @param Route $route
     * @return array
     */
    protected function parseRoute($route)
    {
        return array(
                'pattern'   => $route->getPattern(),
                'params'    => $this->parseRouteParams($route),
                'options'   => $this->parseRouteOptions($route));
    }

    /**
     * Parses route's URL parameters.
     *
     * @param Route $route
     * @return array
     */
    protected function parseRouteParams($route)
    {
        $search = '/{([a-z_0-9]+)}/i';
        $count  = preg_match_all($search, $route->getPattern(), $matches);
        return $count ? $matches[1] : array();
    }

    /**
     * Parses route's configuration options.
     *
     * @param Route $route
     * @return arrays
     */
    protected function parseRouteOptions($route)
    {
        $skipOptions    = array('compiler_class');
        $options        = array();
        foreach ($route->getOptions() as $option => $value) {
            if (! in_array($option, $skipOptions)) {
                $options[$option] = $value;
            }
        }
        return $options;
    }

    /**
     * Returns qualified cache key to store this configuration's routes.
     *
     * @return string
     */
    protected function getCacheKey()
    {
        return "RouteInspector.{$this->cacheNamespace}";
    }
}