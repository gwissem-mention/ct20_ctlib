<?php
namespace CTLib\Controller;

use CTLib\Helper\JavascriptObject;

class ActionGroupController extends DynaPartController
{
    private $numActions = 0;
    private $label = "";

    protected function addDynaPartDependencies($domId, $json, $assetLoader,
        $jsHelper)
    {
        $jsonObject = $json->getObject();

        if ($this->container->has('authorization')) {
            //checking access of object id.
            $authorization = $this->container->get('authorization');
            foreach ($jsonObject->actions as $i => $action) {
                if (! isset($action->objectId)) {
                    throw new \Exception("Action group configuration missing objectId");
                }
                if (! $authorization->hasAccessToObject($action->objectId)) {
                    unset($jsonObject->actions[$i]);
                }
                unset($action->objectId);
            }
        }
        
        $json->setObject($jsonObject);

        $this->numActions = count($jsonObject->actions);
        $this->label = isset($jsonObject->label) ? $jsonObject->label : null;
        unset($jsonObject->label);

        $assetLoader
            ->addAppJs(
                'actionGroup.plugin.js'
            )
            ->addInlineJs(
                '$("#'.$domId.'").actionGroup(' . $json->toJson() . ');'
            )->addAppCss(
                'actionGroup.css'
            );
    }

    protected function buildDynaPartHtml($domAttributes)
    {
        $html = "";
        if ($this->numActions && $this->label) {
            $html .= '<span>' . $this->label . ':</span>';
        }
        $html .= '<div ' . $this->compileDomAttributes($domAttributes) . '></div>';
        return $html;
    }
}
