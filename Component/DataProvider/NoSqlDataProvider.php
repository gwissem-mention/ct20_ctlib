<?php

namespace CTLib\Component\DataProvider;

use Symfony\Component\HttpFoundation\Response;
use CTLib\Util\Arr;
use CTLib\Component\CtApi\CtApiCaller;

/**
 * Facilitates retrieving and processing nosql
 * results into structured data.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class NoSqlDataProvider implements DataAccessInterface
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
     * @param CtApiCaller $apiCaller
     * @param string      $endpoint
     */
    public function __construct($apiCaller, $endpoint) {
        $this->apiCaller    = $apiCaller;
        $this->endpoint     = $endpoint;
        $this->fields       = [];
        $this->filters      = [];
        $this->sorts        = null;
        $this->offset       = 0;
        $this->maxResults   = 0;
    }

    /**
     * Reset the endpoint we will be hitting
     *
     * @param $endpoint
     */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    /**
     * Returns data based on Request.
     *
     * @param Request $request
     *
     * @return array  array('data' => array)
     */
    public function getData($request)
    {
        $queryConfig = $this->getQueryConfig($request);

        return $this->run($queryConfig);
    }

    /**
     * Adds field that will have its value returned in data record.
     *
     * @param string $field
     * @param string $alias
     *
     * @return DataAccessInterface
     */
    public function addField($field, $alias=null)
    {
        if (!$alias) {
            $alias = $field;
        }

        $this->fields[$alias] = $field;

        return $this;
    }

    /**
     * Adds default filter for field.
     *
     * @param string $field
     * @param mixed $filter         Either default filter value or explicit
     *                              filter definition:
     *                                  array('value' => mixed, 'op' => string)
     *
     * @return DataAccessInterface
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

        $this->applyFilter($field, $value, $operator);

        return $this;
    }

    /**
     * Add a sort that will be applied when the data is retrieved.
     *
     * @param string $field
     * @param string $order
     *
     * @return DataAccessInterface
     */
    public function addSort($field, $order)
    {
        $this->sorts[] = '{'.$field.':'.$order.'}';

        return $this;
    }

    /**
     * Add a limit that will be applied when the data is retrieved.
     *
     * @param integer $maxResults
     *
     * @return DataAccessInterface
     */
    public function setMaxResults($maxResults)
    {
        $this->maxResults = $maxResults;

        return $this;
    }

    /**
     * Returns query configuration from Request.
     *
     * @param Request $request
     *
     * @return \stdClass
     */
    protected function getQueryConfig($request)
    {
        $cnf = new \stdClass;
        $cnf->maxResults        = $request->get('maxResults', -1);
        $cnf->offset            = $request->get('offset', 1);
        $cnf->filters           = $request->get('filters', []);
        $cnf->sort              = $request->get('sort', []);

        list(
            $cnf->fields,
            $cnf->aliases
        ) = $this->parseFieldsAndAliases($request->get("fields"));

        return $cnf;
    }

    /**
     * Runs query and returns formatted data.
     *
     * @param \stdClass $queryConfig
     *
     * @return array
     */
    protected function run($queryConfig)
    {
        $this->applyRequestFilters($queryConfig);
        $this->applyRequestSorts($queryConfig);

        if ($queryConfig->maxResults > 0) {
            $this->applyRequestLimit($queryConfig);
        }

        return $this->retrieveData();
    }

    /**
     * Applies filters against QueryBuilder.
     *
     * @param \stdClass $queryConfig
     *
     * @return void
     */
    protected function applyRequestFilters($queryConfig)
    {
        foreach ($queryConfig->filters as $field => $filter) {
            $this->addFilter($field, $filter);
        }
    }

    /**
     * Applies ORDER BY conditions.
     *
     * @param \stdClass $queryConfig
     *
     * @return void
     */
    protected function applyRequestSorts($queryConfig)
    {
        if (!$queryConfig->sorts) {
            return;
        }

        foreach ($queryConfig->sorts as $sort) {
            $this->addSort(
                Arr::mustGet('field', $sort),
                Arr::mustGet('order', $sort)
            );
        }
    }

    /**
     * Applies LIMIT and OFFSET to QueryBuilder.
     *
     * @param \stdClass $queryConfig
     *
     * @return void
     */
    protected function applyRequestLimit($queryConfig)
    {
        if ($queryConfig->maxResults <= 0) {
            return;
        }

        $this->offset = ($queryConfig->offset - 1) * $queryConfig->maxResults;
        $this->maxResults = $queryConfig->maxResults;
    }

    /**
     * Applies standard field filter.
     *
     * @param string $field
     * @param mixed $value
     * @param string $operator   Indicates comparison operation.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function applyFilter($field, $value, $operator)
    {
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
                $this->filters[$field] = '{'.$field.':'.$operator.':'.$value.'}';
                break;

            //TODO: add support for other operators

            default:
                throw new \Exception("Invalid operator: $operator.");
        }
    }

    /**
     * Build the final query string
     *
     * @return string
     */
    protected function constructQueryString()
    {
        // Formulate query string from $this->fields,
        // $this->filters, $this->sorts, $this->maxResults

        $queryString = '';

        return $queryString;
    }

    /**
     * Execute call to API and builds processed result set.
     *
     * @return array    Enumerated array of records (each as their own
     *                  enumerated array of column values).
     */
    protected function retrieveData()
    {
        $result = [];

        // Call API using ApiCaller to retrieve results (array of documents).
        // Once results are retrieved, loop through all records
        // and call $this->processRecord for each.
        // Something along the lines of...

        //$queryString = $this->constructQueryString();
        //$documents = $this->apiCaller->get($this->endpoint, []);

        //foreach ($documents as $document) {
        //    $result[] = $this->processRecord($document);
        //}

        return $result;
    }

    /**
     * Perform iteration on each Record
     *
     * @param mixed $document record data retrieved from API
     *
     * @return array
     */
    protected function processRecord($document)
    {
        $processedRecord = [];

        foreach ($this->fields as $field) {
            $processedRecord[] = Arr::mustGet($field, $document);
        }

        return $processedRecord;
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

    /**
     * Get requested fields and alias that are configured
     * in the javascript
     *
     * @param \stdClass $fieldsConfig
     *
     * @return array array of fields and aliases
     *
     */
    protected function parseFieldsAndAliases($fieldsConfig)
    {
        if (empty($fieldsConfig) || !is_array($fieldsConfig)) {
            return [null, null];
        }

        $fields = $aliases = [];

        foreach ($fieldsConfig as $config) {
            if (is_array($config)) {
                $fields[] = $config[0];
                $aliases[] = Arr::get(1, $config, $config[0]);
            } elseif (is_string($config)) {
                $fields[] = $config;
                $aliases[] = $config;
            }
        }

        return [$fields, $aliases];
    }
}
