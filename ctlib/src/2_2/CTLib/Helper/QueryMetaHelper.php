<?php
namespace CTLib\Helper;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;

/**
 * QueryMetaHelper contains all static method for getting query meta data.
 * This class can provide the all details on all fields for each query, like type, size.
 *
 * All functions starting with "walk" are actually the copies from the doctrine 2.0.
 * So if anything breaks for new version of doctrine, some function has to be re-written.
 */
class QueryMetaHelper
{
    protected static $queryMeta = array();
    protected static $em = null;

    /**
     * Get meta data for the field and table in the given query builder
     *
     * @param QueryBuilder or Query $query The query that developer writes in QueryBuilder or Query.
     * @param string fieldname for which you want to get info
     * @return array contains field all info. if param is an alias, it will return the corresponding
     *      field's info. if the field does not exist, return null
     */
    public static function getFieldMeta($qbr, $fieldName, $em) {
        self::$em = $em;
        $queryParts = self::getQueryMeta($qbr, $em);

        if (preg_match("/^(\w+)\.(\w+)$/", $fieldName, $match)) {
            $idVariable = $match[1];
            $field = $match[2];
        }
        else if (preg_match("/^\w+$/", $fieldName)) {
            $idVariable = null;
            $field = $fieldName;
            $fieldAlias = null;
            foreach ($queryParts["select"] as $select) {
                if (isset($select["alias"]) && $select["alias"] == $fieldName) {
                    $fieldAlias = $fieldName;
                    $field = $select["field"];
                    break;
                }
            }
        }
        else {
            return null;
        }

        $meta = array();
        foreach ($queryParts["entity"] as $entity) {
            $fieldMappings = $entity["meta"]->fieldMappings;
            if (!array_key_exists($field, $fieldMappings)
                || isset($idVariable) && $entity["alias"] != $idVariable)
            {
                continue;
            }
            $map = $fieldMappings[$field];
            $map["entityAlias"] = $entity["alias"];
            $map["entity"] = $entity["entity"];
            $map["entityType"] = $entity["type"];

            if (isset($fieldAlias)) {
                $map["alias"] = $fieldAlias;
            }
            $meta[] = $map;
        }
        return empty($meta) ? null : $meta;
    }

    /**
     * Get detailed info for all parts for DQL query.
     *
     * @param Querybuilder $qbr The query that developer writes in QueryBuilder.
     * @return array The array that stores parsed info for all parts for DQL
     */
    public static function getQueryMeta($qbr, $em) {
        self::$em = $em;
        if ($qbr instanceof QueryBuilder) {
            $ast = $qbr->getQuery()->getAST();
            $dql = $qbr->getQuery()->getDQL();
        }
        else if ($qbr instanceof Query) {
            $ast = $qbr->getAST();
            $dql = $qbr->getDQL();
        }
        else {
            throw new \Exception("function getQueryMeta takes only QueryBuilder or Query class");
        }
        $dqlArr = explode("WHERE", $dql);
        $dql = trim($dqlArr[0]);
        if (!empty(self::$queryMeta[$dql])) return self::$queryMeta[$dql];

        //parse from and join
        $entityArray = array();

        foreach($ast->fromClause->identificationVariableDeclarations as $idDeclare) {
            $from = $idDeclare->rangeVariableDeclaration;
            $fromMeta = self::$em->getClassMetadata($from->abstractSchemaName);
            $fromAssociations = $fromMeta->associationMappings;
            $entityArray[$from->aliasIdentificationVariable] = array(
                "entity" => $from->abstractSchemaName,
                "alias" => $from->aliasIdentificationVariable,
                "meta" => $fromMeta,
                "type" => "primary"
                );
            $joints = $idDeclare->joinVariableDeclarations;
            foreach($joints as $j) {
                $associationField = $j->join->joinAssociationPathExpression->associationField;
                $joinMeta = self::$em->getClassMetadata($fromAssociations[$associationField]["targetEntity"]);
                $entityArray[$j->join->aliasIdentificationVariable] = array(
                    "entity" => $fromAssociations[$associationField]["targetEntity"],
                    "alias" => $j->join->aliasIdentificationVariable,
                    "meta" => $joinMeta,
                    "type" => "join"
                    );
            }
        }

        //parse select
        $selectArray = array();

        foreach($ast->selectClause->selectExpressions as $select) {
            $expression = $select->expression;
            if (is_string($expression)) {
                if (isset($entityArray[$expression])) {
                    foreach($entityArray[$expression]["meta"]->fieldMappings as $field=>$fieldMeta) {
                        $selectArray[] = array(
                            "field" => $fieldMeta["fieldName"],
                            "idVariable" => $entityArray[$expression]["alias"],
                            "fullField" => $entityArray[$expression]["alias"].".".$fieldMeta["fieldName"],
                            "entity" => $entityArray[$expression]["entity"]
                            );
                    }
                }
                continue;
            }
            if ($expression instanceof \Doctrine\ORM\Query\AST\PathExpression) {
                $arr = array(
                    "field"=>$expression->field,
                    "idVariable"=>$expression->identificationVariable,
                    "fullField"=>$expression->identificationVariable . "." .$expression->field
                    );
            }
            if ($expression instanceof \Doctrine\ORM\Query\AST\AggregateExpression) {
                $aggrExpr = $this->walkSimpleArithmeticExpression($expression->pathExpression);
                $fullField =
                    $expression->functionName . '(' . ($expression->isDistinct ? 'DISTINCT ' : '')
                    . $aggrExpr->identificationVariable . "." . $aggrExpr->field . ')'
                    . ($select->fieldIdentificationVariable ? ' AS ' . $select->fieldIdentificationVariable : '');

                $arr = array(
                    "field"=>$expression->pathExpression->field,
                    "idVariable"=>$expression->pathExpression->identificationVariable,
                    "functionName"=>$expression->functionName,
                    "fullField"=>$fullField,
                    "isDistinct"=>$expression->isDistinct
                    );
            }
            if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\FunctionNode) {
                $arr = $this->walkFunction($expression);
            }
            if ($expression instanceof \Doctrine\ORM\Query\AST\Subselect) {
                //not supported
            }
            //adding alias
            if ($select->fieldIdentificationVariable) {
                $arr["alias"] = $select->fieldIdentificationVariable;
            }
            if (isset($arr["idVariable"])) {
                $arr["entity"] = $entityArray[$arr["idVariable"]]["entity"];
            }
            $selectArray[] = $arr;
        }

        //parse order by
        $orderByArray = array();
        if (!empty($ast->orderByClause)) {
            foreach($ast->orderByClause->orderByItems as $item) {
                $orderByArray[] = array(
                    "type" => $item->type,
                    "field" => $item->expression->field,
                    "idVariable"=> $item->expression->identificationVariable
                    );
            }
        }
        self::$queryMeta[$dql] = array("select"=>$selectArray,"entity"=>$entityArray,"orderBy"=>$orderByArray);
        return self::$queryMeta[$dql];
    }

    protected static function walkSimpleArithmeticExpression($simpleArithmeticExpr)
    {
        return ( ! ($simpleArithmeticExpr instanceof \Doctrine\ORM\Query\AST\SimpleArithmeticExpression))
            ? $this->walkArithmeticTerm($simpleArithmeticExpr)
            : implode(
                ' ', array_map(array($this, 'walkArithmeticTerm'), $simpleArithmeticExpr->arithmeticTerms)
                );
    }

    protected static function walkArithmeticTerm($term)
    {
        if (is_string($term)) {
            return $term;
        }

        // Phase 2 AST optimization: Skip processment of ArithmeticTerm
        // if only one ArithmeticFactor is defined
        return ( ! ($term instanceof \Doctrine\ORM\Query\AST\ArithmeticTerm))
            ? $this->walkArithmeticFactor($term)
            : implode(
                ' ', array_map(array($this, 'walkArithmeticFactor'), $term->arithmeticFactors)
                );
    }

    protected static function walkArithmeticFactor($factor)
    {
        if (is_string($factor)) {
            return $factor;
        }

        // Phase 2 AST optimization: Skip processment of ArithmeticFactor
        // if only one ArithmeticPrimary is defined
        return ( ! ($factor instanceof \Doctrine\ORM\Query\AST\ArithmeticFactor))
            ? $this->walkArithmeticPrimary($factor)
            : ($factor->isNegativeSigned() ? '-' : ($factor->isPositiveSigned() ? '+' : ''))
            . $this->walkArithmeticPrimary($factor->arithmeticPrimary);
    }

    protected static function walkArithmeticPrimary($primary)
    {
        if ($primary instanceof \Doctrine\ORM\Query\AST\SimpleArithmeticExpression) {
            return '(' . $this->walkSimpleArithmeticExpression($primary) . ')';
        } else if ($primary instanceof \Doctrine\ORM\Query\AST\PathExpression) {
            return $primary;
        } else if ($primary instanceof \Doctrine\ORM\Query\AST\Literal) {
            return $this->walkLiteral($primary);
        }else if ($primary instanceof \Doctrine\ORM\Query\AST\Node) {
            return $primary->__toString();
        }

        // TODO: We need to deal with IdentificationVariable here
        return '';
    }

    protected static function walkLiteral($literal)
    {
        switch ($literal->type) {
            case \Doctrine\ORM\Query\AST\Literal::STRING:
                return $literal->value;
            case \Doctrine\ORM\Query\AST\Literal::BOOLEAN:
                $bool = strtolower($literal->value) == 'true' ? true : false;
                $boolVal = $bool;
                return $boolVal;
            case \Doctrine\ORM\Query\AST\Literal::NUMERIC:
                return $literal->value;
        }
    }

    protected static function walkStringPrimary($stringPrimary)
    {
        if (is_string($stringPrimary)) {
            return self::$em->getConnection()->quote($stringPrimary);
        }
        else if ($stringPrimary instanceof \Doctrine\ORM\Query\AST\PathExpression) {
            return $stringPrimary->identificationVariable.".".$stringPrimary->field;
        }
        else if ($stringPrimary instanceof \Doctrine\ORM\Query\AST\Node) {
            return $stringPrimary->__toString();
        }
    }

    protected static function walkFunction($expression) {

        if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\AbsFunction
            || $expression instanceof \Doctrine\ORM\Query\AST\Functions\SqrtFunction)
        {
            $simpleArithmeticExpressionField = $this->walkSimpleArithmeticExpression($expression->simpleArithmeticExpression);
            return array(
                "functionName"=>$expression->name,
                "fullField"=>$expression->name."(".$simpleArithmeticExpressionField.")",
                );
        }
        if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\ConcatFunction) {

            $firstStringPrimary = $expression->firstStringPrimary;
            $secondStringPrimary = $expression->secondStringPrimary;

            if ($firstStringPrimary instanceof \Doctrine\ORM\Query\AST\PathExpression
                && $secondStringPrimary instanceof \Doctrine\ORM\Query\AST\PathExpression)
            {
                $firstStringPrimaryField = $firstStringPrimary->identificationVariable.".".$firstStringPrimary->field;
                $secondStringPrimaryField = $secondStringPrimary->identificationVariable.".".$secondStringPrimary->field;
            }

            $fullField = "";
            if ($firstStringPrimary instanceof \Doctrine\ORM\Query\AST\PathExpression
                && !$secondStringPrimary instanceof \Doctrine\ORM\Query\AST\PathExpression)
            {
                $firstStringPrimaryField = $firstStringPrimary->identificationVariable.".".$firstStringPrimary->field;
                $secondStringPrimaryFieldArray = $this->walkFunction($secondStringPrimary);
                $secondStringPrimaryField = $secondStringPrimaryFieldArray["fullField"];
            }

            if (!$firstStringPrimary instanceof \Doctrine\ORM\Query\AST\PathExpression
                && $secondStringPrimary instanceof \Doctrine\ORM\Query\AST\PathExpression)
            {
                $firstStringPrimaryFieldArray = $this->walkFunction($firstStringPrimary);
                $firstStringPrimaryField = $firstStringPrimaryFieldArray["fullField"];
                $secondStringPrimaryField = $secondStringPrimary->identificationVariable.".".$secondStringPrimary->field;
            }

            $fullField = $expression->name."(".$firstStringPrimaryField.",".$secondStringPrimaryField.")";
            return array(
                "functionName"=>$expression->name,
                "fullField"=>$fullField,
                );
        }
        if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\LengthFunction)
        {
            $stringPrimaryField = $this->walkSimpleArithmeticExpression($expression->stringPrimary);
            return array(
                "functionName"=>$expression->name,
                "fullField"=>$expression->name."(".$stringPrimaryField.")",
                );
        }
        if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\LowerFunction
            || $expression instanceof \Doctrine\ORM\Query\AST\Functions\UpperFunction
            || $expression instanceof \Doctrine\ORM\Query\AST\Functions\TrimFunction)
        {
            $stringPrimaryField = $this->walkStringPrimary($expression->stringPrimary);
            return array(
                "functionName"=>$expression->name,
                "fullField"=>$expression->name."(".$stringPrimaryField.")",
                );
        }
        if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\SubstringFunction) {

            $optionalSecondSimpleArithmeticExpression = null;

            if (isset($expression->secondSimpleArithmeticExpression)) {
                $optionalSecondSimpleArithmeticExpression = $this->walkSimpleArithmeticExpression($expression->secondSimpleArithmeticExpression);
            }

            $fullField = $expression->name . "(" .
                $this->walkStringPrimary($expression->stringPrimary) . "," .
                $this->walkSimpleArithmeticExpression($expression->firstSimpleArithmeticExpression) .
                (isset($optionalSecondSimpleArithmeticExpression)?",".$optionalSecondSimpleArithmeticExpression:"")
                .")";

            return array(
                "functionName"=>$expression->name,
                "fullField"=>$fullField,
                );
        }

        if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\CurrentDateFunction) {
            //not supported
            //return $sqlWalker->getConnection()->getDatabasePlatform()->getCurrentDateSQL();
        }
        if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\CurrentTimeFunction) {
            //not supported
            //return $sqlWalker->getConnection()->getDatabasePlatform()->getCurrentTimeSQL();
        }
        if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\CurrentTimestampFunction) {
            //not supported
            //return $sqlWalker->getConnection()->getDatabasePlatform()->getCurrentTimestampSQL();
        }
        if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\SizeFunction) {
            //not supported
        }
        if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\LocateFunction) {
            //not supported
            //return $sqlWalker->getConnection()->getDatabasePlatform()->getLocateExpression(
            //    $sqlWalker->walkStringPrimary($this->secondStringPrimary), // its the other way around in platform
            //    $sqlWalker->walkStringPrimary($this->firstStringPrimary),
            //    (($this->simpleArithmeticExpression)
            //        ? $sqlWalker->walkSimpleArithmeticExpression($this->simpleArithmeticExpression)
            //        : false
            //        )
            //    );
            //;
        }
        if ($expression instanceof \Doctrine\ORM\Query\AST\Functions\ModFunction) {
            //not supported
            //$firstSimpleArithmeticExpression;
            //$secondSimpleArithmeticExpression;
            //return $sqlWalker->getConnection()->getDatabasePlatform()->getModExpression(
            //    $sqlWalker->walkSimpleArithmeticExpression($this->firstSimpleArithmeticExpression),
            //    $sqlWalker->walkSimpleArithmeticExpression($this->secondSimpleArithmeticExpression)
            //    );
        }
    }
}
