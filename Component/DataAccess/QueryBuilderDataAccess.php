<?php

namespace CTLib\Component\DataAccess;

use CTLib\Component\DataAccess\Filter\DataAccessFilterInterface;

/**
 * Facilitates retrieving and processing nosql
 * results into structured data.
 *
 * @author Joe Imhoff   <jimhoff@celltrak.com>
 * @author David McLean <dmclean@celltrak.com>
 */
class QueryBuilderDataAccess implements DataAccessInterface
{
    /**
     * @var Doctrine\ORM\QueryBuidler
     */
    protected $queryBuilder;

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var array
     */
    protected $filters = [];

    /**
     * @var array
     */
    protected $sorts = [];

    /**
     * @var integer
     */
    protected $offset = 0;

    /**
     * @var integer
     */
    protected $maxResults = 0;

    /**
     * @param CtApiCaller   $apiCaller
     * @param string        $endpoint
     */
    public function __construct($queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * {@inheritdoc}
     *
     * Returns data based on Request.
     *
     * @return array
     */
    public function getData()
    {
        $this->buildQuery();

        return $this->queryBuilder->getQuery()->getArrayResult();
    }

    /**
     * {@inheritdoc}
     *
     * Adds field that will have its value returned in data record.
     *
     * @param string   $field
     *
     * @return DataAccessInterface
     *
     * @throws \Exception
     */
    public function addField($field)
    {
        if (isset($this->fields[$field])) {
            throw new \Exception("Field has already been added");
        }

        $this->fields[] = $field;

        return $this;
    }

    /**
     * Iterates over passed values and passes them to addFilter
     *
     * @param array filter values
     * @return DataAccessInterface
     */
    public function addFilters(array $filters)
    {
        foreach ($filters as $filter)
        {
            $this->addFilter(
                $filter['field'],
                $filter['value'],
                isset($filter['op']) ? $filter['op'] : 'eq'
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Adds default filter for field.
     *
     * @param string|callable $field
     * @param mixed|null      $value
     * @param string|null     $operator
     *
     * @return DataAccessInterface
     */
    public function addFilter($field, $value=null, $operator='eq')
    {
        if (!is_callable($field) && !$value) {
            throw new \InvalidArgumentException('Invalid filter value');
        }

        if (is_array($value)) {
            if (!in_array($operator, ['eq', 'in', "notIn"])) {
                throw new \InvalidArgumentException("Array value only supports 'eq' or 'in' or 'notIn' operator.");
            }
            if ($operator == 'eq') {
                $operator = 'in';
            }
        }

        $this->filters[] = [
            'field' => $field,
            'op'    => $operator,
            'value' => $value
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Add a sort that will be applied when the data is retrieved.
     *
     * @param string      $field
     * @param int|string  $order
     *
     * @return DataAccessInterface
     */
    public function addSort($field, $order)
    {
        if (strtoupper($order) != self::SORT_ASC
            && strtoupper($order) != self::SORT_DESC) {
            throw new \InvalidArgumentException('Invalid sort value - must be one of (ASC, DESC)');
        }

        $this->sorts[] = [$field, $order];

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Add a limit that will be applied when the data is retrieved.
     *
     * @param integer $maxResults
     *
     * @return DataAccessInterface
     */
    public function setMaxResults($maxResults)
    {
        if ($maxResults < 0) {
            throw new \InvalidArgumentException('maxResults cannot be negative');
        }

        $this->maxResults = $maxResults;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Set what record to start at when the data is retrieved.
     *
     * @param integer $offset
     *
     * @return DataAccessInterface
     */
    public function setOffset($offset)
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('offset cannot be negative');
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Return field names
     *
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * local method for building the query per the set values
     */
    protected function buildQuery()
    {
        $queryCounter = 0;

        $statementGen = function($filter) use (&$queryCounter) {
            $valueName = "queryValue{$queryCounter}";
            switch ($filter['op']) {
                case 'eq':
                    $statement = "{$filter['field']} = :$valueName";
                    break;
                case 'in':
                    $statement = "{$filter['field']} IN (:$valueName)";
                    break;
                case 'notIn':
                    $statement = "{$filter['field']} NOT IN (:$valueName)";
                    break;
                case 'gt':
                    $statement = "{$filter['field']} > :$valueName";
                    break;
                case 'lt':
                    $statement = "{$filter['field']} < :$valueName";
                    break;
                case 'gte':
                    $statement = "{$filter['field']} >= :$valueName";
                    break;
                case 'lte':
                    $statement = "{$filter['field']} <= :$valueName";
                    break;
            }

            $queryCounter++;
            return [$statement, $valueName];
        };

        foreach ($this->filters as $filter) {
            list($statement, $valueName) = $statementGen($filter);
            $this->queryBuilder
                ->andWhere($statement)
                ->setParameter($valueName, $filter['value']);
        }

        foreach ($this->sorts as $order) {
            list($field, $direction) = $order;
            $this->queryBuilder->orderBy($field, $direction);
        }

        if ($this->offset !== 0) {
            $this->queryBuilder->setFirstResult($this->offset);
        }

        if ($this->maxResults !== 0) {
            $this->queryBuilder->setMaxResults($this->maxResults);
        }
    }

    /**
     * Applies filter handler.
     *
     * @param DataAccessFilterInterface|callable $handler
     * @param mixed $value
     *
     * @return void
     */
    protected function applyFilterHandler($handler, $value)
    {
        if (is_callable($handler)) {
            call_user_func($handler, $this, $value);
        } elseif ($handler instanceof DataAccessFilterInterface) {
            $handler->apply($this, $value);
        }
    }

    /**
     * Extracts filter into its individual components.
     *
     * @param array $filter
     *
     * @return array
     */
    protected function extractFilter($filter)
    {
        $field      = Arr::mustGet('field', $filter);
        $value      = Arr::get('value', $filter);
        $operator   = Arr::get('op', $filter, 'eq');
        return [$field, $value, $operator];
    }
}
