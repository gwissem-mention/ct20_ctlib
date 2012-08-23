<?php
namespace CTLib\Twig\Extension;

use CTLib\Util\Arr;

class DynaPartTokenParser extends \Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(\Twig_Token $token)
    {
        $stream = $this->parser->getStream();

        // Required format:
        // {% dynapart DynaPartClass %}
        // or
        // {% dynapart "Bundle:DynaPartClass" %}
        if ($stream->test(\Twig_Token::STRING_TYPE)) {
            $dynaPartName = $stream->expect(\Twig_Token::STRING_TYPE)->getValue();
        } else {
            $dynaPartName = $stream->expect(\Twig_Token::NAME_TYPE)->getValue();    
        }

        // Supports optional routeName:
        // {% dynapart DynaPartClass : routeName }
        $routeName = null;
        if ($stream->test(\Twig_Token::PUNCTUATION_TYPE, ':')) {
            $stream->next();
            $routeName = $stream->expect(\Twig_Token::NAME_TYPE)->getValue();
        }

        // Supports optional DOM attributes in tag formatted as:
        // {% dynapart DynaPartClass : routeName | id="id" name="name" ... %}

        //parse dynapart's dom attribute key value pair additions
        $domAttributes = array();
        if ($stream->test(\Twig_Token::PUNCTUATION_TYPE, '|')) {
            $stream->next();
            $domAttributes = $this->parseDomAttributes($stream, \Twig_Token::BLOCK_END_TYPE);
        }

        // Advance past '%}'.
        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        $jsonBody = $this->parser->subparse(
            function(\Twig_Token $token) {
                return $token->test(array("enddynapart"));
            }
        );

        $stream->expect(\Twig_Token::NAME_TYPE, 'enddynapart');
        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        return new DynaPartNode(
            $jsonBody,
            array(
                'dynaPartName'  => $dynaPartName,
                'routeName'     => $routeName,
                'domAttributes' => $domAttributes,
                'fileName'      => $stream->getFilename()
            ),
            $token->getLine(),
            $this->getTag()
        );
    }

    protected function parseKeyValuePairs($stream, $endToken)
    {
        $result = array();

        while (! $stream->test($endToken)) {
            $key = $stream->expect(\Twig_Token::NAME_TYPE)->getValue();
            $stream->expect(\Twig_Token::OPERATOR_TYPE, '=');
            $value = $stream->expect(\Twig_Token::STRING_TYPE)->getValue();

            $filter = null;
            if ($stream->test(\Twig_Token::PUNCTUATION_TYPE, '|')) {
                $stream->next();
                $filter = $stream->expect(\Twig_Token::NAME_TYPE)->getValue();
            }

            $result[$key] = array($key, $value, $filter);
        }

        return $result;
    }

    protected function parseDomAttributes($stream)
    {
        $doms = $this->parseKeyValuePairs($stream, \Twig_Token::BLOCK_END_TYPE);
        if (is_null($doms)) $doms = array();
        return $doms;
    }

    /**
     * Parses next DOM attribute from stream.
     * Expected format:
     *      attribute="value"
     *
     * @param Twig_Stream $stream Parser's stream
     * @return array        array($attributeName, $value, $filter)
     */
    protected function parseDomAttribute($stream)
    {
        $attributeName = $stream->expect(\Twig_Token::NAME_TYPE)->getValue();
        $stream->expect(\Twig_Token::OPERATOR_TYPE, '=');
        $value = $stream->expect(\Twig_Token::STRING_TYPE)->getValue();

        if ($stream->test(\Twig_Token::PUNCTUATION_TYPE, '|')) {
            // Applying filter function to attribute value.
            // Used for things like translation.
            $stream->next();
            $filter = $stream->expect(\Twig_Token::NAME_TYPE)->getValue();
        } else {
            $filter = null;
        }

        return array($attributeName, $value, $filter);
    }

    /**
     * Parse twig variable block 
     * Example: {{ "activities.action.export"|trans }}
     *
     * @param Twig_Stream $stream Parser's stream
     * @param array $filterCallbacks Array that holds callback function for each twig filter
     * @example if trans and transchoice filter are handled, array looks like: array("trans"=>function() {}, "transchoice"=>function(){})
     * @return mixed Value of twig variable. could be filtered by
     *
     */    
    protected function parseVariableBlock($stream, $filterCallbacks = null)
    {
        $stream->expect(\Twig_Token::VAR_START_TYPE);
        //Todo: change it to sub parse
        if ($stream->test(\Twig_Token::NUMBER_TYPE) || $stream->test(\Twig_Token::STRING_TYPE)) {
            $twigVariable = $stream->next()->getValue();
        }

        $filterParams = array($twigVariable);
        
        //if there is filter
        if ($stream->test(\Twig_Token::PUNCTUATION_TYPE, '|')) {
            list($filterName, $filterParams) = $this->parseVariableFilter($stream);

            array_unshift($filterParams, $twigVariable);

            if (!empty($filterCallbacks) && is_array($filterCallbacks)) {
                $callback = Arr::get($filterName, $filterCallbacks);

                if (!is_null($callback) && is_callable($callback)) {
                    $twigVariable = call_user_func_array($callback, $filterParams);
                }
                else {
                    throw new \Twig_Error_Runtime("Filter {$filterName}'s callback function is invalid");
                }
            }
        }

        $stream->expect(\Twig_Token::VAR_END_TYPE);
        return $twigVariable;
    }

    /**
     * Parse Variable's Filter
     *
     * @param Twig_TokenStream $stream parser's stream object
     * @return array array(filterName, filterParams)
     *
     */
    protected function parseVariableFilter($stream)
    {
        $stream->expect(\Twig_Token::PUNCTUATION_TYPE, '|');
        
        $filterName = $stream->expect(\Twig_Token::NAME_TYPE)->getValue();

        $filterParams = array();

        if ($stream->test(\Twig_Token::PUNCTUATION_TYPE, '(')) {
            $stream->next();
            //parse parameters
            do {
                if ($stream->test(\Twig_Token::NUMBER_TYPE) || $stream->test(\Twig_Token::STRING_TYPE)) {
                    $filterParams[] = $stream->next()->getValue();
                }
                elseif ($stream->test(\Twig_Token::PUNCTUATION_TYPE, "{")) {
                    $filterParams[] = $this->parseAssociateArray($stream);
                }
            } while ($stream->test(\Twig_Token::PUNCTUATION_TYPE, ',')?$stream->next()->getValue():false);

            $stream->expect(\Twig_Token::PUNCTUATION_TYPE, ')');
        }

        return array($filterName, $filterParams);
    }

    /**
     * Parse Associate Array
     * Expected format: {"key1": "val1", "key2": 2}
     *
     * @param Twig_TokenStream $stream parser's stream object
     * @return array Parsed associate array
     *
     */
    protected function parseAssociateArray($stream)
    {
        $stream->expect(\Twig_Token::PUNCTUATION_TYPE, '{');

        $transParams = array();

        do {
            if ($stream->test(\Twig_Token::STRING_TYPE)
                || $stream->test(\Twig_Token::NAME_TYPE)
            ) {
                $key = $stream->next()->getValue();
            }
            else {
                throw new Twig_Error_Syntax("The key in associate array is invalid", $this->getLine());
            }

            $stream->expect(\Twig_Token::PUNCTUATION_TYPE, ':');

            if ($stream->test(\Twig_Token::STRING_TYPE) 
                || $stream->test(\Twig_Token::NUMBER_TYPE)
                || $stream->test(\Twig_Token::NAME_TYPE)
            ) {
                $val = $stream->next()->getValue();
            }
            else {
                throw new Twig_Error_Syntax("The value in associate array is invalid", $this->getLine());
            }

            $transParams[$key] = $val;

        } while ($stream->test(\Twig_Token::PUNCTUATION_TYPE, ',')?$stream->next()->getValue():false);

        $stream->expect(\Twig_Token::PUNCTUATION_TYPE, '}');
        return $transParams;
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     */
    public function getTag()
    {
        return 'dynapart';
    }

}