<?php

namespace CTLib\Component\DataProvider;

use CTLib\Util\Arr;

/**
 * Facilitates retrieving and processing nosql
 * results into structured data.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class CtNoSqlDataAccess implements DataInputInterface
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
        // Call API using ApiCaller to retrieve results (array of documents).
        $queryString = $this->constructQueryString();

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
     * @return DataInputInterface
     *
     * @throws \Exception
     */
    public function addField($field, $alias=null)
    {
        if (!$alias) {
            $alias = $field;
        }

        if (array_key_exists($alias, $this->fields)) {
            throw new \Exception('Ambiguous field name given');
        }

        $this->fields[$alias] = 1;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Adds default filter for field.
     *
     * @param string $field
     * @param mixed $filter         Either default filter value or explicit
     *                              filter definition:
     *                                  array('value' => mixed, 'op' => string)
     *
     * @return DataInputInterface
     */
    public function addFilter($field, $filter)
    {
        if (!is_array($filter) || !isset($filter['value'])) {
            $filter = ['value' => $filter];
        }

        list($value, $operator, $cacheOnly) = $this->extractFilter($filter);

        if ($cacheOnly) {
            // This filter doesn't need to be applied.
            // It's just used to preserve the front-end filter UI.
            return $this;
        }

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
     * @param string $field
     * @param string $order
     *
     * @return DataInputInterface
     */
    public function addSort($field, $order)
    {
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
     * @return DataInputInterface
     */
    public function setMaxResults($maxResults)
    {
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
     * @return DataInputInterface
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Build the final query string
     *
     * @return array
     */
    protected function constructQueryString()
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

    /**
     * Extracts filter into its individual components.
     *
     * @param array $filter
     *
     * @return array
     */
    protected function extractFilter($filter)
    {
        $value      = Arr::mustGet('value', $filter);
        $operator   = Arr::get('op', $filter, 'eq');
        $cacheOnly  = Arr::get('cacheOnly', $filter, false);
        return [$value, $operator, $cacheOnly];
    }
}
