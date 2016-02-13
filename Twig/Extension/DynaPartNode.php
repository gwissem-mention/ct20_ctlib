<?php
namespace CTLib\Twig\Extension;

use CTLib\Util\Util,
    CTLib\Helper\JavascriptObject,
    CTLib\Helper\JavascriptPrimitive;

class DynaPartNode extends \Twig_Node
{
    //store temporary context variables
    //when debug mode is off, twig parser will parse twig variables into
    //temporary variables, $setTemps is used to capture those.
    //later all parsed expression for temporary variables will be rendered.
    protected $setTemps;

    public function __construct(\Twig_NodeInterface $jsonBody, $attributes, $lineno, $tag)
    {
        $attributes["id"] = md5($attributes["fileName"] . $attributes["dynaPartName"] . $lineno);
        $notes = array("jsonBody" => $jsonBody);
        $this->setTemps = array();

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
        $json = trim($json);

        if (!empty($this->setTemps)) {
            $uniqueSetTemps = array_unique($this->setTemps);
            foreach($uniqueSetTemps as $temp) {
                $compiler->write($temp."\n");
            }
        }

        if (true || empty($json)) {
            // MT + SL + ZK on Mar 21, 2013:
            // Disabling jsonObject caching because it universally caches a
            // post Twig-evaluated JSON string for every session. This is
            // extremely problematic because many Twig calls in the JSON will
            // evaluate to session-specific values (i.e., memberId).
            $compiler->write('$jsonObject = new \CTLib\Helper\JavascriptObject(' . trim($json) . ");"."\n");
        }
        else {
            $cacheKey = md5($json);
            $compiler
                ->write('$cacheKey = $this->getContext($context, "app")->getRequest()->getRequestUri() . "' . $cacheKey . '";'."\n")
                ->write('$cache = $this->env->getExtension("dynapart")->getCache();'."\n")
                ->write('if ($cache->has($cacheKey)) {'."\n")
                ->indent()
                ->write('$jsonObject = $cache->get($cacheKey);'."\n")
                ->outdent()
                ->write('}'."\n")
                ->write('else {'."\n")
                ->indent()
                ->write('$jsonObject = new \CTLib\Helper\JavascriptObject(' . trim($json) . ");"."\n")
                ->write('$cache->set($cacheKey, $jsonObject);'."\n")
                ->outdent()
                ->write('}'."\n");
        }
        $compiler
            ->write('$uriObj = new \Symfony\Component\HttpKernel\Controller\ControllerReference("' . $qualifiedAction . '", ')
            ->raw("array(")
            ->raw('"id" => ')->repr($this->getAttribute("id"))->raw(', ')
            ->raw('"routeName" => ')->repr($this->getAttribute("routeName"))->raw(', ')
            ->raw('"domAttributes" => ')->repr($this->getAttribute("domAttributes"))->raw(', ')
            ->raw('"json" => $jsonObject')->raw(',')
            ->raw('"_frontendRoute" => $this->env->getExtension("dynapart")->getRequest()->attributes->get("_route")')
            ->write("), array());")
            ->write('echo $this->env->getExtension("actions")->renderUri($uriObj);');
    }

    /**
     * Convert JsonNode To String
     *
     * @param Twig_Compiler $compiler
     * @param Twig_NodeInterface $jsonNode
     * @return string
     *
     */
    public function convertJsonNodeToString(\Twig_Compiler $compiler, \Twig_NodeInterface $jsonNode)
    {
        $result = "";

        if ($jsonNode instanceof \Twig_Node_SetTemp) {
            $setTempSource = $this->getTwigExpressionSource($compiler, $jsonNode);
            //remove debugging line number
            $this->setTemps[] = preg_replace('/\\s*\\/\\/\\s*line\\s+\\d*[\\r\\n]/', '', $setTempSource);
            return "";
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

        if ($iterator->count() != 0) {
            foreach ($iterator as $key => $node) {
                $result .=
                    (empty($result)?"":' . ') .
                    $this->convertJsonNodeToString($compiler, $node);
            }
        }

        return $result;
    }

    /**
     * convert twig node object into string
     *
     * @param Twig_Compiler $compiler
     * @param Twig_NodeInterface $node
     * @return string expression string
     *
     */
    private function getTwigExpressionSource(\Twig_Compiler $compiler, \Twig_NodeInterface $node)
    {
        $twigEnv = $compiler->getEnvironment();
        $tempCompiler = new \Twig_Compiler($twigEnv);
        $node->compile($tempCompiler);
        return $tempCompiler->getSource();
    }

}