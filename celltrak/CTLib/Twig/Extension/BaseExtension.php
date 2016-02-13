<?php
namespace CTLib\Twig\Extension;



class BaseExtension extends \Twig_Extension
{
    
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var RouteInspector
     */
    protected $routeInspector;


    /**
     * @param ServiceContainer $container
     */
    public function __construct($container)
    {
        $this->request          = $container->get('request');   
        $this->routeInspector   = $container->get('route_inspector');
        $this->logger           = $container->get('logger');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'base';
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            'pageDOMId' => new \Twig_Function_Method($this, 'pageDOMId'),
            'routeUrl'  => new \Twig_Function_Method($this, 'routeUrl'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            'bool'  => new \Twig_Filter_Method($this, 'bool'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTests()
    {
        return array(
            'string' => new \Twig_Test_Function('is_string')
        );
    }


    public function pageDOMId()
    {
        $controller = $this->request->attributes->get('_controller');

        if (strpos($controller, '::')) {
            list($class, $action) = explode('::', $controller);    
            $class  = \CTLib\Util\Util::shortClassName($class);
            $class  = str_replace('Controller', '', $class);
            $action = str_replace('Action', '', $action);
        } else {
            list($bundle, $class, $action) = explode(':', $controller);
        }
        
        return "{$class}-{$action}";
    }

    /**
     * get route url from name in the twig
     *
     * @param string $routeName route name
     * @return string route url
     *
     */
    public function routeUrl($routeName)
    {
        return $this->routeInspector->getPattern($routeName);
    }

    /**
     * Proxy for bool filter
     *
     * @param mixed $value
     * @return string bool ("true"|"false")
     *
     */    
    public function bool($value)
    {
        return $value === false ? "false" : "true";
    }
    
    

}