<?php
namespace CTLib\Controller;

use CTLib\Controller\BaseController,
    Symfony\Component\HttpFoundation\Response,
    CTLib\Helper\JavascriptObject,
    CTLib\Helper\JavascriptPrimitive;

abstract class DynaPartController extends BaseController
{
    /**
     * Builds HTML GUI for DynaPart.
     *
     * @param array $domAttributes      As array($attribute => $value).
     * @return string
     */
    abstract protected function buildDynaPartHtml($domAttributes);

    /**
     * Adds assets and JS pass-thrus required by DynaPart.
     *
     * @param string $domId
     * @param string $json      JSON configuration options.
     * @param TwigLazyLoaderListener $assetLoader
     * @param JsHelper $jsHelper
     *
     * @return void
     */
    abstract protected function addDynaPartDependencies($domId, $json, $assetLoader, $jsHelper);

    /**
     * Singluar action supported by controller to render DynaPart.
     *
     * @param string $id            Unique ID of DynaPart.
     * @param string $routeName     Name of route providing the dynamic data.
     * @param array $domAttributes  array(array($attribute, $value, $filter))
     * @param string $json          JSON configuration options.
     *
     * @return Response
     */
    final public function showAction($id, $routeName, $domAttributes, $json)
    {
        
        $domAttributes = $this->processDynaPartDomAttributes($domAttributes);

        if (! isset($domAttributes['id'])) {
            $domAttributes['id'] = $id;
        }
        
        $this->addDynaPartDependencies(
            $domAttributes['id'],
            $this->compileDynaPartJson($id, $routeName, $json, $domAttributes['id']),
            $this->get('twig.lazyload.listener'),
            $this->js()
        );
        return new Response($this->buildDynaPartHTML($domAttributes));
    }

    /**
     * Reformats DOM attributes as passed from Twig parser and applies any
     * filter functions.
     *
     * @param array $domAttributes  array(array($attribute, $value, $filter))
     * @return array    array($attribute => $value)
     */
    protected function processDynaPartDomAttributes($domAttributes)
    {
        $processedAttributes = array();

        foreach ($domAttributes AS $attribute) {
            list($name, $value, $filter) = $attribute;

            if ($filter) {
                $value = $this->{"apply{$filter}Filter"}($value);
            }

            $processedAttributes[$name] = $value;
        }
        return $processedAttributes;
    }

    /**
     * Compiles JSON configuration adding standard options for data source URL
     * and cached settings.
     *
     * @param string $id            Unique ID of DynaPart.
     * @param string $routeName     Name of route providing the dynamic data.
     * @param string $json          JSON configuration options.
     *
     * @return string
     */
    protected function compileDynaPartJson($id, $routeName, $json, $domId)
    {
        if (empty($routeName)) return $json;

        $routeUrl = $this->getRoute($routeName)->getPattern();
        $extra = array(
            $this->getDynaPartSourceOptionName() => $routeUrl
        );

        $cacheId = $json->cacheId;

        //if cacheId is set to be false, do not need to get cached options
        if ($cacheId !== false) {
            $extra = array_merge(
                $extra,
                $this->getDynaPartCachedOptions($cacheId?:$id, $domId)
            );
        }

        //grab preset parameters from session
        $sessData = $this->get('app_session')->getDynapartPreSetParameters(
            $this->currentController(),
            $domId
        );
        if ($sessData) {
            $processedSessData = $this->processPresetParameters($domId, $sessData);
            if ($processedSessData) {
                $extra = array_merge(
                    $extra,
                    $processedSessData
                );
            }
            $this->get('app_session')->clearDynapartPreSetParameters(
                $this->currentController(),
                $domId
            );
        }

        $json->merge($extra);

        return $json;
    }

    /**
     * Returns JavaScript configuration option name for data source URL.
     *
     * @return string
     */
    protected function getDynaPartSourceOptionName()
    {
        return 'source';
    }

    /**
     * Returns cached JavaScript configuration options.
     *
     * @param string $cacheId    Unique ID of DynaPart.
     * @param string $domId configured in dom attribute
     * @return array
     */
    protected function getDynaPartCachedOptions($cacheId, $domId)
    {
        return array(
            'cacheId' => $cacheId
        );
    }

    /**
     * Applies translation filter on DOM attribute value.
     *
     * @param string $value     DOM attribute value.
     * @return string
     */
    protected function applyTransFilter($msg)
    {
        return $this->trans($msg);
    }

    protected function applyTranschoiceFilter($msg, $count, $params)
    {
        return $this->transChoice($msg, $count, $params);
    }
    /**
     * Compiles DOM attributes into HTML string.
     *
     * @param array $domAttributes  As array($attribute => $value).
     * @return string
     */
    protected function compileDomAttributes($domAttributes)
    {
        return implode(
            " ",
            array_map(
                function($name, $value) {
                    return $name . '="' . $value .'"';
                }, 
                array_keys($domAttributes), 
                array_values($domAttributes)
            )
        );
    }

    /**
     * Process the preset Parameters from session, apply business
     * logics, and turn them into inital parameters that dynapart needs
     *
     * @param array $sessionData data from session
     * @return array the parameter array that dynapart can understand
     *
     */    
    protected function processPresetParameters($domId, $sessionData)
    {
        return array();
    }

    /**
     * Indicates whether recordset session storage (filters, pagination, etc.) is valid.
     *
     * @return boolean
     */
    protected function isSessionStorageValid()
    {
        $lastVisitedRouteName = $this->get("app_session")->getLastVisitedRouteName();
        $currentRouteName = $this->currentRouteName();

        // Storage is invalid if returning to page after session timeout.
        // (storage won't event be there because session was invalidated)
        if (! $lastVisitedRouteName) { return false; }

        // Storage is valid if reloading from same page.
        if ($currentRouteName == $lastVisitedRouteName) { return true; }

        // Storage is valid if previous page's parent is this page.
        $parentRouteName = $this->getRouteOption($lastVisitedRouteName, 'parent');
        if ($currentRouteName == $parentRouteName) { return true; }

        return false;
    }

}
