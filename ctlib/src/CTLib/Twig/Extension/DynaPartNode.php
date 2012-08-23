<?php
namespace CTLib\Twig\Extension;

use CTLib\Util\Util,
    CTLib\Helper\JavascriptObject,
    CTLib\Helper\JavascriptPrimitive;

class DynaPartNode extends \Twig_Node
{

    public function __construct(\Twig_NodeInterface $jsonBody, $attributes, $lineno, $tag)
    {
        $attributes["id"] = md5($attributes["fileName"] . $attributes["dynaPartName"] . $lineno);
        $notes = array("jsonBody" => $jsonBody);

        parent::__construct($notes, $attributes, $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    public function compile(\Twig_Compiler $compiler)
    {
        $dynaPartName = $this->getAttribute("dynaPartName");

        if (strpos($dynaPartName, ':') === false) {
            $dynaPartName = "CTLib:{$dynaPartName}";
        }

        $qualifiedAction = "{$dynaPartName}:show";
        
        $json = $this->convertJsonNodeToString($compiler, $this->getNode("jsonBody"));
        $cacheKey = md5($json);

        $compiler
            ->write('$cache = $this->env->getExtension("dynapart")->getCache();'."\n")
            ->write('if ($cache->has("'.$cacheKey.'")) {'."\n")
                ->indent()
                ->write('$jsonObject = $cache->get("'.$cacheKey.'");'."\n")
            ->outdent()
            ->write('}'."\n")
            ->write('else {'."\n")
                ->indent()
                ->write('$jsonObject = new \CTLib\Helper\JavascriptObject(' . trim($json) . ");"."\n")
                ->write('$cache->set("'.$cacheKey.'", $jsonObject->toObject());'."\n")
            ->outdent()
            ->write('}'."\n")
            ->write('echo $this->env->getExtension("actions")->renderAction("'.$qualifiedAction.'", ')
            ->raw("array(")
            ->raw('"id" => ')->repr($this->getAttribute("id"))->raw(', ')
            ->raw('"routeName" => ')->repr($this->getAttribute("routeName"))->raw(', ')
            ->raw('"domAttributes" => ')->repr($this->getAttribute("domAttributes"))->raw(', ')
            ->raw('"json" => $jsonObject')->raw(',')
            ->raw('"_frontendRoute" => $this->env->getExtension("dynapart")->getRequest()->attributes->get("_route")')
            ->write("), array());");
    }


    public function convertJsonNodeToString(\Twig_Compiler $compiler, \Twig_NodeInterface $jsonNode)
    {
        $result = "";

        if (empty($jsonNode)) {
            return $result;
        }

        if ($jsonNode instanceof \Twig_Node_Expression) {
            return $this->getTwigExpressionSource($compiler, $jsonNode);
        }

        if ($jsonNode instanceof \Twig_Node_Print) {
            return $this->getTwigExpressionSource($compiler, $jsonNode->getNode('expr'));
        }

        if ($jsonNode instanceof \Twig_Node_Text) {
            $jsonStr = $jsonNode->getAttribute('data');
            return "'" . addcslashes($jsonStr, "'") . "'";
        }

        if ($jsonNode instanceof \Twig_Node_If) {
            for ($i = 0; $i < count($jsonNode->getNode('tests')); $i+=2) {
                if ($i > 0) {
                    $result .= ") : ";
                }
                else {
                    $result .= "\n(";
                }

                $conditions = $this->convertJsonNodeToString($compiler, $jsonNode->getNode('tests')->getNode($i));
                $ifBody = $this->convertJsonNodeToString($compiler, $jsonNode->getNode("tests")->getNode($i + 1));
                $result .= $conditions . " ? (\n" . $ifBody;
            }

            if ($jsonNode->hasNode('else') && null !== $jsonNode->getNode('else')) {
                $elseBody = $this->convertJsonNodeToString($compiler, $jsonNode->getNode('else'));
                $result .= 
                    ") : (\n" .
                    $elseBody .
                    ")\n";
            }
            else {
                $result .= ') : ""';
            }

            $result .= ")\n";

            return $result;
        }

        $iterator = $jsonNode->getIterator();
        if ($iterator->count() <= 0) {
            throw new \Exception("Not supported Twig Expression");
        }

        foreach ($iterator as $key => $node) {
            $result .= 
                (empty($result)?"":' . ') . 
                $this->convertJsonNodeToString($compiler, $node);
        }

        return $result;
    }

    private function getTwigExpressionSource(\Twig_Compiler $compiler, \Twig_NodeInterface $node)
    {
        $tempCompiler = new \Twig_Compiler($compiler->getEnvironment());
        $node->compile($tempCompiler);
        return $tempCompiler->getSource();
    }

}