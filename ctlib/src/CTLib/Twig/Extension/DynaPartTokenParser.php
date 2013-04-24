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
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     */
    public function getTag()
    {
        return 'dynapart';
    }

}