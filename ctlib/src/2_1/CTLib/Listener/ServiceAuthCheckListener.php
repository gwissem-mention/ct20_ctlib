<?php
namespace CTLib\Listener;

use Symfony\Component\EventDispatcher\Event,
    Symfony\Component\HttpKernel\HttpKernelInterface,
    Symfony\Component\HttpFoundation\Response;


class ServiceAuthCheckListener
{

    const AUTH_HEADER   = 'CT_AUTH';
    const AUTH_TOKEN    = '%0K-r6i5S026;R#xcX7o?5!LwS09.&a-Ilxw^syQeul&7P1^3-';
    const ROUTE_OPTION  = 'enableServiceAuthCheck';
    

    public function __construct($router)
    {
        $this->router = $router;
    }

    public function onKernelController(Event $event)
    {
        // Only want to run authorization check on primary requests for routes
        // that require it.
        if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST
            || ! $this->routeRequiresAuthorization($event)) {
            return;
        }

        // Run authorization check.
        $passedToken = $event->getRequest()->server->get(
            'HTTP_' . self::AUTH_HEADER
        );
        if ($passedToken !== self::AUTH_TOKEN) {
            $event->setController(
                function() { return new Response('Access Denied', 403); }
            );
            $event->stopPropagation();
        }
    }

    /**
     * Indicates whether route requires authorization check.
     *
     * @param Event $event
     * @return boolean
     */
    protected function routeRequiresAuthorization(Event $event)
    {
        $routeName  = $event->getRequest()->attributes->get('_route');
        $route      = $this->router->getRouteCollection()->get($routeName);
        return $route->getOption(self::ROUTE_OPTION) === true;
    }

}