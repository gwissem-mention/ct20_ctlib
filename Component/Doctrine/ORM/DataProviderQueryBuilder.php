<?php
namespace CTLib\Component\Doctrine\ORM;

use CTLib\Util\Arr,
    Doctrine\ORM\Query\ParameterTypeInferer,
    Doctrine\ORM\Tools\Pagination\Paginator;

class DataProviderQueryBuilder extends QueryBuilder
{
    protected $modelFields = array();

    /**
     * Adds multiple model fields used in this query.
     *
     * @param array $field,...  See addModelField for $field definition.
     * @return DataProviderQueryBuilder
     */
    public function addToModel($field)
    {
        foreach (func_get_args() AS $field) {
            $this->addModelField($field);
        }
        return $this;
    }

    /**
     * Adds single model field used in this query.
     *
     * @param mixed $field      Can either be string: "{entityAlias}.{field}"
     *                          or array(
     *                              'src' => $source,
     *                              'as' => $alias,
     *                              'objectId' => $objectId).
     *                          - $source can be either "{entityAlias}.{field}"
     *                          or callable. It is required.
     *                          - $alias must be a string used to represent
     *                          field in result JSON. It's optional when
     *                          $source is a string.
     *                          - $objectId is a string used to check
     *                          session's access to field. It's optional.
     * @return DataProviderQueryBuilder
     * @throws Exception    If $alias already in use.
     */
    protected function addModelField($field)
    {
        list($source, $alias, $objectId) = $this->extractModelField($field);

        if (isset($this->modelFields[$alias])) {
            throw new \Exception("Already added field with alias: $alias");
        }

        $this->modelFields[$alias] = array($source, $objectId);
        return $this;
    }

    /**
     * Returns model used in this query.
     *
     * @return array    array($field1, $field2, ...)
     *                  See addModelField for $field definition.
     */
    public function getModel()
    {
        return $this->modelFields;
    }

    /**
     * Returns aliases of fields in model used in this query.
     *
     * @return array    array($alias1, $alias2, ...)
     */
    public function getModelAliases()
    {
        return array_keys($this->modelFields);
    }

    /**
     * Extracts model field into its components.
     *
     * @param array $field  See addModelField for $field definition.
     *
     * @return array    array($source, $alias, $objectId)
     */
    protected function extractModelField($field)
    {
        if (is_string($field)) {
            $source     = $field;
            $alias      = $this->getModelFieldAlias($source);
            $objectId   = null;
        } elseif (is_array($field)) {
            $source     = Arr::mustGet('src', $field);
            $alias      = Arr::get('as', $field) ?: $this->getModelFieldAlias($source);
            $objectId   = Arr::get('objectId', $field);
        } else {
            throw new \Exception("Invalid field");
        }
        return array($source, $alias, $objectId);
    }

    /**
     * Returns alias for model field.
     *
     * @param string $source    Field's source (i.e., "a.activityId").
     *
     * @return string
     * @throws Exception    If $source is invalid.
     */
    protected function getModelFieldAlias($source)
    {
        if (! is_string($source)) {
            throw new \Exception("\$source must be a string");
        }
        $tokens = explode('.', $source);
        if (count($tokens) != 2) {
            throw new \Exception("Invalid \$source: $source");
        }
        return $tokens[1];
    }

    /**
     * Returns clone of this QueryBuilder configured to return result count
     * instead of actual results.
     *
     * @return QueryBuilder
     */
    public function getResultTotal()
    {
        $paginator = new Paginator($this);
        return count($paginator);
    }

    /**
     * get paginatable result. user developer must apply foreach loop on the result
     *
     * @return Paginator paginatable result
     *
     */
    public function getPaginatedResult($fetchJoinCollection = true)
    {
        $paginator = new Paginator($this, $fetchJoinCollection);
        $paginator->setUseOutputWalkers(false);
        return $paginator;
    }
}
