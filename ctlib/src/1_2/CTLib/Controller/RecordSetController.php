<?php
namespace CTLib\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller,
    CTLib\Util\Arr;

/**
 * Base controller for all RecordSets.
 *
 * @uses DynaPartController
 * @todo KG: Storing values in _SESSION is not consistent across whole site.
 */
class RecordSetController extends DynaPartController
{
    /**
     * Add dependencies for DynaPart
     *
     * @param string                 $domId
     * @param string                 $json
     * @param TwigLazyLoaderListener $assetLoader
     * @param JsHelper               $jsHelper
     *
     * @return void
     */
    protected function addDynaPartDependencies($domId, $json, $assetLoader, $jsHelper)
    {
        $jsHelper
            ->addTranslation('recordSet*');

        //load needed css and javascript lib and script
        $assetLoader
            ->addAppJs(
                'checkHelper.plugin.js',
                'actionGroup.plugin.js',
                'loadingBox.plugin.js',
                'recordSet.plugin.js',
                'abridge.plugin.js',
                'tooltip.plugin.js'
            )
            ->addInlineJs('$("#'.$domId.'").recordSet('.$json.');')
            ->addAppCss(
                'recordSet.css',
                'actionGroup.css',
                'loadingBox.css',
                'abridge.css',
                'tooltip.css'
            );
    }

    /**
     * Create the html for the DynaPart.
     *
     * @param array $domAttributes
     *
     * @return string
     */
    protected function buildDynaPartHtml($domAttributes)
    {
        return
            '<div ' .
            $this->compileDomAttributes($domAttributes) .
            '></div>';
    }

    /**
     * Get the cached options for the DynaPart.
     *
     * @param string $cacheId
     * @param string $domId
     *
     * @return array Of options.
     */
    protected function getDynaPartCachedOptions($cacheId, $domId)
    {
        $twig           = $this->get('twig.extension.dynapart');
        $currentFilters = (array) $twig->getParameter($domId, "currentFilters");

        $options = array(
            'cacheId'           => $cacheId,
            'currentPage'       => 1,
            'currentSort'       => array(),
            'currentFilters'    => array()
        );

        if (!$this->isSessionStorageValid()) {
            $this->session()->remove($cacheId);
        }
        else {
            $sessionStorage = $this->session()->get($cacheId);

            if ($sessionStorage) {
                if ($sessionStorage->currentPage) {
                    $options['currentPage'] = (int) $sessionStorage->currentPage;
                }
                if ($sessionStorage->sorts) {
                    $options['currentSort'] = $sessionStorage->sorts;
                }
                if ($sessionStorage->filters) {
                    $options['currentFilters'] = $sessionStorage->filters;
                }
            }
        }

        $options['currentFilters'] = array_merge(
            $options['currentFilters'],
            $currentFilters
        );

        return $options;
    }

}
