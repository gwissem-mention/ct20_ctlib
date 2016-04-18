<?php

namespace CTLib\Component\DataAccess;

use CTLib\Component\DataAccess\Filter\DataAccessFilterInterface;
use CTLib\Util\Arr;

/**
 * Facilitates retrieving and processing nosql
 * results into structured data.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class CtApiDocumentDataAccess implements DataAccessInterface
{
    
    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @var CtApiCaller
     */
    protected $apiCaller;

    /**
     * @var array
     */
    protected $fields;

    /**
     * @var array
     */
    protected $filters;

    /**
     * @var array
     */
    protected $sorts;

    /**
     * @var integer
     */
    protected $offset;

    /**
     * @var integer
     */
    protected $maxResults;


    /**
     * @param CtApiCaller   $apiCaller
     * @param string        $endpoint
     */
    public function __construct($apiCaller, $endpoint)
    {
        $this->apiCaller    = $apiCaller;
        $this->endpoint     = $endpoint;
        $this->fields       = [];
        $this->filters      = [];
        $this->sorts        = null;
        $this->offset       = 0;
        $this->maxResults   = 0;
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
        $data = [];

        // Call API using ApiCaller to retrieve results (array of documents).
        $queryString = $this->constructQueryParams();

        foreach ($this->filters as $filter) {
            list(
                $field, $value) = $this->extractFilter($filter);

            $this->applyFilterHandler($field, $value);
        }

        $documents = $this->apiCaller->get($this->endpoint, $queryString);

        return json_decode($documents, true);
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

        $this->sorts[$field] = $order;

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

    /**
     * Build the final query string
     *
     * @return array
     */
    protected function constructQueryParams()
    {
        // Formulate query string from $this->fields,
        // $this->filters, $this->sorts, $this->offset, $this->maxResults
        $queryString                = [];
        $queryString['fields']      = $this->fields;
        $queryString['criteria']    = $this->filters;
        $queryString['sort']        = $this->sorts;
        $queryString['offset']      = $this->offset;
        $queryString['numRecords']  = $this->maxResults;

        return $queryString;
    }
    
}
