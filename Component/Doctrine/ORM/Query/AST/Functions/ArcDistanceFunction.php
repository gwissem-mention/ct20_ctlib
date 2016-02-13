<?php
namespace CTLib\Component\Doctrine\ORM\Query\AST\Functions;

use \Doctrine\ORM\Query\AST\Functions\FunctionNode,
    \Doctrine\ORM\Query\Lexer,
    CTLib\Helper\LocalizationHelper;

class ArcDistanceFunction extends FunctionNode
{
    public $centerLatArithmeticExpression;
    public $centerLngArithmeticExpression;
    public $addressLatArithmeticExpression;
    public $addressLngArithmeticExpression;
    public $unit;

    /**
     * @override
     */
    public function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker)
    {
        $centerLat = $sqlWalker->walkSimpleArithmeticExpression($this->centerLatArithmeticExpression);
        $centerLng = $sqlWalker->walkSimpleArithmeticExpression($this->centerLngArithmeticExpression);
        $addressLat = $sqlWalker->walkSimpleArithmeticExpression($this->addressLatArithmeticExpression);
        $addressLng = $sqlWalker->walkSimpleArithmeticExpression($this->addressLngArithmeticExpression);
        $unit = $this->unit;

        if ($unit == LocalizationHelper::DISTANCE_UNIT_MILE) {
            $R = 3959;
        }
        else if ($unit == LocalizationHelper::DISTANCE_UNIT_KILOMETER) {
            $R = 6371;
        }
        else {
            throw new \Exception("unit is invalid");
        }

        return "(". 
            "{$R} * acos(".
                "cos(radians({$centerLat})) * ".
                "cos(radians({$addressLat})) * ".
                "cos(radians({$addressLng}) - radians({$centerLng})) + ".
                "sin(radians({$centerLat})) * sin(radians({$addressLat}))".
            ")".
        ")";
    }

    /**
     * @override
     */
    public function parse(\Doctrine\ORM\Query\Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        
        $this->centerLatArithmeticExpression = $parser->SimpleArithmeticExpression();
        $parser->match(Lexer::T_COMMA);

        $this->centerLngArithmeticExpression = $parser->SimpleArithmeticExpression();
        $parser->match(Lexer::T_COMMA);

        $this->addressLatArithmeticExpression = $parser->SimpleArithmeticExpression();
        $parser->match(Lexer::T_COMMA);

        $this->addressLngArithmeticExpression = $parser->SimpleArithmeticExpression();
        $parser->match(Lexer::T_COMMA);

        //$this->unitStringPrimary = $parser->StringPrimary();
        $parser->match(Lexer::T_IDENTIFIER);
        $this->unit = $parser->getLexer()->token['value'];

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}