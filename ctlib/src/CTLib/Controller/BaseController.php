<?php

namespace CTLib\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller,
    CTLib\Component\HttpFoundation\JsonResponse,
    CTLib\Component\HttpFoundation\PdfResponse,
    CTLib\Util\Util,
    CTLib\Component\Doctrine\ORM\EntityManagerEvent,
    CTLib\Component\Pdf\HtmlPdf;

/**
 * BaseController
 */
abstract class BaseController extends Controller
{
    /**
     * @var string $currentBundle
     */
    private $currentBundle;

    /**
     * @var string $currentController
     */
    private $currentController;

    /**
     * @var Request $request
     */
    private $request;

    /**
     * @var Session $session
     */
    private $session;

    /**
     * @var Logger $logger
     */
    private $logger;


    /**
     * Shortcut to retrieve kernel's runtime from the service container.
     *
     * @return Runtime
     */
    protected function runtime()
    {
        return $this->get('kernel')->getRuntime();
    }

    /**
     * Shortcut to retrieve an entity manager from the service container.
     *
     * @param string $name        Uses 'default' if not passed.
     *
     * @return EntityManager
     */
    protected function em($name='default')
    {
        return $this->get("doctrine.orm.${name}_entity_manager");
    }

    /**
     * Replaces entity manager in service container with a new one.
     *
     * @param EntityManager $em
     * @param string $name          Entity manager name. Uses 'default' if not
     *                              passed.
     *
     * @return void
     */
    protected function replaceEm($em, $name='default')
    {
        $this->container->set("doctrine.orm.{$name}_entity_manager", $em);
        $this->get('event_dispatcher')->dispatch(
            'entity_manager.replace',
            new EntityManagerEvent($name, $em)
        );
    }

    /**
     * Shortcut to retrive a repository from the default entity manager.
     *
     * @param string $repositoryName
     * @param string $bundle            Uses controller's bundle if not passed.
     * @return EntityRepository
     */
    protected function repo($repositoryName, $bundle=null)
    {
        return $this->emRepo($this->em(), $repositoryName, $bundle);
    }

    /**
     * Shortcut to retrieve a repository for a passed entity manager.
     *
     * @param EntityManager $em
     * @param string $repositoryName
     * @param string $bundle            Uses controller's bundle if not passed.
     * @return EntityRepository
     */
    protected function emRepo($em, $repositoryName, $bundle=null)
    {
        $bundle = $bundle ?: $this->currentBundle();
        $qualifiedName = "${bundle}Bundle:${repositoryName}";
        return $em->getRepository($qualifiedName);
    }

    /**
     * Shortcut to calling EntityManager::persist($entity); EntityManager::flush().
     * Uses the default entity manager.
     *
     * @param Entity $entity,...    Will only call EntityManager::flush after
     *                              after persisting all passed entities.
     *                              You can also pass multiple entities as array.
     * @return void
     */
    protected function save($entity)
    {
        $entities = is_array($entity) ? $entity : func_get_args();
        return $this->emSave($this->em(), $entities);
    }

    /**
     * Shortcut to calling EntityManager::persist($entity); EntityManager::flush().
     * Uses the passed EntityManager.
     *
     * @param EntityManager $em
     * @param Entity $entity,...    Will only call EntityManager::flush after
     *                              after persisting all passed entities.
     *                              You can also pass multiple entities as array.
     * @return void
     */
    protected function emSave($em, $entity)
    {
        $entities = is_array($entity) ? $entity : func_get_args();
        foreach ($entities AS $entity) {
            $em->persist($entity);
        }
        $em->flush();
    }

    /**
     * Shortcut to retrieve Request object from the service container.
     *
     * @return Request
     */
    protected function request()
    {
        return $this->get('request');
    }

    /**
     * Get a parameter from the container.
     *
     * @param mixed $param
     *
     * @return mixed
     */
    protected function getParameter($param)
    {
        return $this->container->getParameter($param);
    }

    /**
     * Shorcut to retrieve current route name from the Request object.
     *
     * @return string
     */
    protected function getRouteName()
    {
        return $this->request()->attributes->get('_route');
    }

    /**
     * Indicates whether POST method used for request.
     * @return boolean
     */
    protected function isPostRequest()
    {
        return $this->request()->getMethod() == 'POST';
    }

    /**
     * Indicates whether GET method used for request.
     * @return boolean
     */
    protected function isGetRequest()
    {
        return $this->request()->getMethod() == 'GET';
    }

    /**
     * Indicates whether PUT method used for request.
     * @return boolean
     */
    protected function isPutRequest()
    {
        return $this->request()->getMethod() == 'PUT';
    }

    /**
     * Indicates whether DELETE method used for request.
     * @return boolean
     */
    protected function isDeleteRequest()
    {
        return $this->request()->getMethod() == 'DELETE';
    }

    /**
     * Indicates whether request type is XHR (Ajax).
     *
     * @return boolean
     */
    protected function isAjaxRequest()
    {
        return $this->request()->isXmlHttpRequest();
    }

    /**
     * Returns value for POST parameter.
     * @param string $key,...   If multiple keys requested, will return values
     *                          as associative array of key => value.
     *                          You can also pass multiple keys as array.
     * @return mixed    Returns value or null if key not present.
     */
    protected function fromPost($key)
    {
        if (is_array($key) || func_num_args() > 1) {
            $keys   = is_array($key) ? $key : func_get_args();
            $values = array();
            foreach ($keys AS $key) {
                $values[$key] = $this->fromPost($key);
            }
            return $values;
        } else {
            return $this->request()->request->get($key);
        }
    }

    /**
     * Returns value for GET parameter.
     * @param string $key,...   If multiple keys requested, will return values
     *                          as associative array of key => value.
     *                          You can also pass multiple keys as array.
     * @return mixed    Returns value or null if key not present.
     */
    protected function fromGet($key)
    {
        if (is_array($key) || func_num_args() > 1) {
            $keys   = is_array($key) ? $key : func_get_args();
            $values = array();
            foreach ($keys AS $key) {
                $values[$key] = $this->fromGet($key);
            }
            return $values;
        } else {
            return $this->request()->query->get($key);
        }
    }

    /**
     * Returns value for URL parameter (i.e., 'myPage/{param1}').
     * @param string $key,...   If multiple keys requested, will return values
     *                          as associative array of key => value.
     *                          You can also pass multiple keys as array.
     * @return mixed    Returns value or null if key not present.
     */
    protected function fromUrl($key)
    {
        if (is_array($key) || func_num_args() > 1) {
            $keys   = is_array($key) ? $key : func_get_args();
            $values = array();
            foreach ($keys AS $key) {
                $values[$key] = $this->fromUrl($key);
            }
            return $values;
        } else {
            return $this->request()->attributes->get($key);
        }
    }

    /**
     * Indicates whether $key is present in POST parameters.
     * @param  string $key,...   You can also pass multiple keys as array.
     * @return boolean
     */
    protected function inPost($key)
    {
        if (is_array($key) || func_num_args() > 1) {
            $keys   = is_array($key) ? $key : func_get_args();
            $found  = true;
            foreach ($keys AS $key) {
                $found = $found && $this->fromPost($key);
            }
            return $found;
        } else {
            return $this->fromPost($key) !== null ? true : false;
        }
    }

    /**
     * Indicates whether $key is present in GET parameters.
     * @param string $key,...   You can also pass multiple keys as array.
     * @return boolean
     */
    protected function inGet($key)
    {
        if (is_array($key) || func_num_args() > 1) {
            $keys   = is_array($key) ? $key : func_get_args();
            $found  = true;
            foreach ($keys AS $key) {
                $found = $found && $this->fromGet($key);
            }
            return $found;
        } else {
            return $this->fromGet($key) !== null ? true : false;
        }
    }

    /**
     * Indicates whether $key is present in URL parameters.
     * @param string $key,...   You can also pass multiple keys as array.
     * @return boolean
     */
    protected function inUrl($key)
    {
        if (is_array($key) || func_num_args() > 1) {
            $keys   = is_array($key) ? $key : func_get_args();
            $found  = true;
            foreach ($keys AS $key) {
                $found = $found && $this->fromUrl($key);
            }
            return $found;
        } else {
            return $this->fromUrl($key) !== null ? true : false;
        }
    }

    /**
     * Shortcut to retrieve Session object from the service container.
     * @return Session
     */
    protected function session()
    {
        return $this->get('session');
    }

    /**
     * Sets 1+ key/value pairs into session.
     * @param array $values     Pass as array($key => $value).
     * @return void
     */
    public function sessionSet($values)
    {
        foreach ($values AS $key => $value) {
            $this->session()->set($key, $value);
        }
        return;
    }

    /**
     * Prepare a message.
     *
     * @param string $from     Message from address.
     * @param string $to       To list.
     * @param string $subject  Message subject.
     * @param string $body     Message text body.
     * @param string $htmlBody Message html body.
     *
     * @return message
     */
    protected function createMessage($from, $to, $subject, $body, $htmlBody=null)
    {
        $message = $this->get('message')
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->setBody($body);

        if ($htmlBody) {
            $message->addPart($htmlBody, 'text/html');
        }

        return $message;
    }

    /**
     * Create a message using a template.
     *
     * @param string $from
     * @param mixed $to
     * @param string $subject
     * @param string $template
     * @param array  $params
     *
     * @return \Swift_Message
     */
    protected function createMessageForTemplate($from, $to, $subject, $template, $params)
    {
        if (!array_key_exists('locale', $params) || empty($params['locale'])) {
            $params['locale'] = $this->get('translator')->getLocale();
        }

        $subject = $this->trans($subject, array(), 'messages', $params['locale']);

        $body = $this->renderView(
            $this->buildFullTemplateName($template, 'txt', '_Messages'),
            $params
        );

        $htmlTemplateName = $this->buildFullTemplateName(
            $template,
            'html',
            '_Messages'
        );

        if ($this->get('templating')->exists($htmlTemplateName)) {
            $htmlBody = $this->renderView(
                $htmlTemplateName,
                $params
            );
        } else {
            $htmlBody = null;
        }

        return $this->createMessage($from, $to, $subject, $body, $htmlBody);
    }

    /**
     * Shortcut to retrieve Logger object from the service container.
     * @return Logger
     */
    protected function logger()
    {
        return $this->get('logger');
    }

    /**
     * Shortcut to retrieve LocalizationHelper object from the service
     * container.
     *
     * @return LocalizationHelper
     */
    public function localizer()
    {
        return $this->get('localizer');
    }

    /**
     * Shortcut to Translator::trans.
     *
     * @param string    $id             Message identifier.
     * @param array     $parameters     String substitutions.
     * @param string    $domain         Message domain.
     * @param string    $locale         Locale to translate into.
     *
     * @return string
     */
    protected function trans($id, array $parameters=array(), $domain='messages', $locale=null)
    {
        return $this->get('translator')->trans(
            $id,
            $parameters,
            $domain,
            $locale);
    }

    /**
     * Shortcut to Translator::transChoice.
     *
     * @param string    $id             Message identifier.
     * @param int       $number         Determines pluralization form.
     * @param array     $parameters     String substitutions.
     * @param string    $domain         Message domain.
     * @param string    $locale         Locale to translate into.
     *
     * @return string
     */
    protected function transChoice($id, $number, array $parameters=array(), $domain='messages', $locale=null)
    {
        return $this->get('translator')->transChoice(
            $id,
            $number,
            $parameters,
            $domain,
            $locale);
    }

    /**
     * Shortcut to retrieve Javascript helper service from container.
     * @return JavascriptHelper
     */
    protected function js()
    {
        return $this->get('js');
    }

    /**
     * Wrapper for Controller::render that automatically builds qualified
     * name of view template (i.e., GatewayBundle:Login:index.html.twig).
     * Only need to pass 'index' and render will fill in the rest.
     *
     * @param string    $view           Root name of view template.
     * @param array     $parameters     Array of value replacements.
     * @param Response  $response
     *
     * @return Response
     */
    public function render($view, array $parameters=array(), \Symfony\Component\HttpFoundation\Response $response=null)
    {
        return parent::render(
            $this->buildFullTemplateName($view),
            $parameters,
            $response
        );
    }

    /**
     * Renders standard service response.
     *
     * @param boolean $success
     * @param string $message
     * @param array $validationErrors
     * @param boolean $isModal
     *
     * @return JsonResponse
     */
    public function renderServiceResponse($success, $message='', $validationErrors=array(), $isModal=false)
    {
        return new JsonResponse(
            array(
                'success'           => $success,
                'message'           => $message,
                'validationErrors'  => $validationErrors,
                'isModal'           => $isModal
            )
        );
    }

    /**
     * Renders PDF Response, let browser show it inline or force download
     *
     * @param string $view name of view template.
     * @param array $parameters parameters for rendering template
     * @param string $fileName downloadable file name
     * @param string $destination show pdf within the browser or force download
     * @return PdfResponse 
     *
     */   
    public function renderPdf($view, array $parameters=array(), 
        $fileName="celltrak.pdf", $destination=PdfResponse::DESTINATION_INLINE)
    {
        $html = $this->renderView(
            $this->buildFullTemplateName($view),
            $parameters
        );

        $htmlPdf = new HtmlPdf($this->get("kernel"));

        return new PdfResponse($htmlPdf->render($html), $fileName, $destination);
    }
    
    /**
     * Determines bundle name (sans 'Bundle') of this controller.
     * @return string
     */
    public function currentBundle()
    {
        if (! isset($this->currentBundle)) {
            $this->extractCurrentClassParts();
        }
        return $this->currentBundle;
    }

    /**
     * Determines controller name (sans 'Controller').
     * @return string
     */
    public function currentController()
    {
       if (! isset($this->currentController)) {
           $this->extractCurrentClassParts();
       }
       return $this->currentController;
    }

    /**
     * Returns name of requested route.
     * this route will always be the frontend route.
     *
     * @return string
     */
    public function currentRouteName()
    {
        //if this function is called in dynapart contoller,
        //_route returns "_internal", so it need to look at
        //_frontendRoute for real front end route.
        //_frontendRoute is the variable that has been injected by
        //dynapart twig template
        $route = $this->request()->attributes->get('_route');
        if ($route !== "_internal") {
            return $route;
        }
        return $this->request()->attributes->get('_frontendRoute');
    }

    /**
     * Extracts this controller's name and its bundle's name.
     * It's a helper method called by currentBundle() or currentController()
     * if their respective variables aren't already set.
     * @return void
     */
    protected function extractCurrentClassParts()
    {
        // Class name will include Namespace\Class.
        // Specifically, we're going to extract (SomeBundle)\Controller\(SomeController).
        $className       = get_class($this);
        $classNameTokens = explode('\\', $className);

        // Get the current bundle by pulling out the first token.
        $this->currentBundle = array_shift($classNameTokens);
            // Drop 'Bundle' off the name. Methods in this class assume
            // bundle is passed without the 'Bundle' suffix.
            $this->currentBundle = str_replace(
                'Bundle',
                '',
                $this->currentBundle
                );

        // Get the current controller by pulling out the last token.
        $this->currentController = array_pop($classNameTokens);
        // Drop 'Controller' off the name.
        $this->currentController = str_replace(
            'Controller',
            '',
            $this->currentController
        );
    }

    /**
     * Shortcut to retrieving route object from router in service container.
     *
     * @param string $routeName
     *
     * @return Route
     * @throws Exception    If route not found.
     */
    protected function getRoute($routeName)
    {
        $route = $this->get('router')->getRouteCollection()->get($routeName);

        if (! $route) {
            throw new \Exception("Invalid route name: $routeName.");
        }
        return $route;
    }

    /**
     * Returns option for route.
     *
     * @param string $routeName
     *
     * @return mixed        Returns NULL if option not found for route.
     * @throws Exception    If route not found.
     */
    protected function getRouteOption($routeName, $option)
    {
        return $this->getRoute($routeName)->getOption($option);
    }

    /**
     * Returns URL parameters for route (i.e., '/myPage/{param1}/{param2}').
     *
     * @param string $routeName
     *
     * @return array
     * @throws Exception    If route not found.
     */
    protected function getRouteParams($routeName)
    {
        $pattern = $this->getRoute($routeName)->getPattern();
        $matchCount = preg_match_all('/{([a-z_0-9]+)}/i', $pattern, $matches);
        return $matchCount ? $matches[1] : array();
    }


    /**
     * Assemble the host url (scheme, domainname, and subdir)
     *
     * @return string
     */
    protected function buildHostUrl()
    {
        $routerContext = $this->get('router')->getContext();

        return $routerContext->getScheme() . '://' .
            $routerContext->getHost() .
            $routerContext->getBaseUrl();
    }

    /**
     * Assembles absolute url based on this host's url.
     *
     * @param string $path
     * @return string
     */
    protected function buildAbsoluteUrl($path)
    {
        return $this->buildHostUrl() . Util::prepend($path, '/');
    }

    /**
     * Creates a full template filename using what you give it and defaults.
     *
     * @param string $view
     * @param string $type
     * @param string $controller
     * @param string $bundle
     *
     * @return string
     */
    protected function buildFullTemplateName($view, $type='html', $controller=null, $bundle=null)
    {
        // Full path of template name
        if (preg_match("/\w+:\w+:\w+.\w+.twig/", $view, $match)) {
            return $view;
        }

        if (! strpos($view, '.twig')) {
            $view .= ".{$type}.twig";
        }
        
        return ( $bundle ? ucfirst($bundle) : $this->currentBundle() ) . 'Bundle'
            . ':' . ( $controller ?: $this->currentController() )
            . ':' . $view;
    }

}
