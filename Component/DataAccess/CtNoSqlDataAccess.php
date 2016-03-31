<?php

namespace CTLib\Component\DataAccess;

use CTLib\Util\Arr;

/**
 * Facilitates retrieving and processing nosql
 * results into structured data.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class CtNoSqlDataAccess implements DataAccessInterface
{
    /**
     * Constants for sort order
     */
    const SORT_ASC  = 1;
    const SORT_DESC = -1;

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
        // Call API using ApiCaller to retrieve results (array of documents).
        $queryString = $this->constructQueryParams();

        return $this->apiCaller->get($this->endpoint, $queryString);
    }

    /**
     * {@inheritdoc}
     *
     * Adds field that will have its value returned in data record.
     *
     * @param string|callable   $field
     * @param string            $alias
     *
     * @return DataAccessInterface
     *
     * @throws \Exception
     */
    public function addField($field, $alias=null)
    {
        $this->fields[$field] = 1;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Adds default filter for field.
     *
     * @param string $field
     * @param mixed $value          Either default filter value or explicit
     *                              filter definition:
     *                                  array('value' => mixed, 'op' => string)
     *                              or callback
     * @param string $operator
     *
     * @return DataAccessInterface
     */
    public function addFilter($field, $value, $operator='eq')
    {
        if (!$value) {
            return $this;
        }

        if (is_array($value)) {
            if (!in_array($operator, ['eq', 'in', "notIn"])) {
                throw new \Exception("Array value only supports 'eq' or 'in' operator.");
            }
            if ($operator == 'eq') {
                $operator = 'in';
            }
        }

        switch ($operator) {
            case 'eq':  // Equals
            case 'gt':  // Greater than
            case 'gte': // Greater than equal to
            case 'lt':  // Less than
            case 'lte': // Less than equal to
                $this->filters[$field] = ['$'.$operator => $value];
                break;

            case 'neq': // Not equal to
                $this->filters[$field] = ['$ne' => $value];
                break;

            case 'in':  // in
                $this->filters[$field] = ['$in:['.implode(',',$value).']'];
                break;

            case 'nin':  // not in
                $this->filters[$field] = ['$nin:['.implode(',',$value).']'];
                break;

            case 'like%':
            case '%like':
            case '%like%':
                $this->filters[$field] = [':regex: /^'.$value.'$/'];
                break;

            default:
                throw new \Exception("Invalid filter operator: $operator.");
        }

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
        if ($order != 1 && $order != -1
            && strtoupper($order) != 'ASC'
            && strtoupper($order) !== 'DESC') {
            throw new \Exception('Invalid sort value - must be one of (1, -1, ASC, DESC)');
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
            $maxResults = 0;
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
            $offset = 0;
        }

        $this->offset = $offset;

        return $this;
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
