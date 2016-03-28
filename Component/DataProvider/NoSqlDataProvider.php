<?php

namespace CTLib\Component\DataProvider;

use CTLib\Component\CtApi\CtApiCaller;
use Symfony\Component\HttpFoundation\Response;
use CTLib\Util\Arr;
use CTLib\Component\HttpFoundation\JsonResponse;

/**
 * Facilitates retrieving and processing nosql
 * results into structured data.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
class NoSqlDataProvider implements DataAccessInterface, DataOutputInterface
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
     * This provider returns data results as a JSON response.
     *
     * @param $request
     *
     * @return JsonResponse
     */
    public function getResults($request)
    {
        $results = [];

        // Retrieve the data
        $data = $this->getData($request);

        // Flatten out our data
        foreach ($data as $document) {
            $result[] = $this->transform($document);
        }

        return new JsonResponse($results);
    }

    /**
     * {@inheritdoc}
     *
     * Returns data based on Request.
     *
     * @param Request $request
     *
     * @return array
     */
    public function getData($request)
    {
        $queryConfig = $this->getQueryConfig($request);

        return $this->run($queryConfig);
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
        if (is_callable($field) && !$alias) {
            throw new \Exception('alias is required for callback');
        }

        if (!$alias) {
            $alias = $field;
        }

        if (array_key_exists($alias, $this->fields)) {
            throw new \Exception('Ambiguous field name given');
        }

        $this->fields[$alias] = $field;

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
     * {@inheritdoc}
     *
     * Add a sort that will be applied when the data is retrieved.
     *
     * @param string $field
     * @param string $order
     *
     * @return DataAccessInterface
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
        $cnf->maxResults        = $request->get('rowsPerPage', -1);
        $cnf->offset            = $request->get('currentPage', 1);
        $cnf->filters           = $request->get('filters', []);
        $cnf->sort              = $request->get('sorts', []);
        $cnf->cachePages        = $request->get('cachedPage', 0);

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

        $max = $queryConfig->maxResults
            + $queryConfig->cachePages * $queryConfig->maxResults;

        $this->offset = ($queryConfig->offset - 1) * $queryConfig->maxResults;
        $this->maxResults = $max;
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
            case 'gt':  // Greater than
            case 'gte': // Greater than equal to
            case 'lt':  // Less than
            case 'lte': // Less than equal to
                $this->filters[$field] = $field.'{$'.$operator.':'.$value.'}';
                break;

            case 'neq': // Not equal to
                $this->filters[$field] = $field.'{$ne:'.$value.'}';
                break;

            case 'in':  // in
                $this->filters[$field] = $field.'{$in:['.$value.']}';
                break;

            case 'nin':  // not in
                $this->filters[$field] = $field.'{$nin:['.$value.']}';
                break;

            case 'like%':
            case '%like':
            case '%like%':
                $this->filters[$field] = $field.'{$regex: /^'.$value.'$/}';
                break;

            default:
                throw new \Exception("Invalid filter operator: $operator.");
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
        // $this->filters, $this->sorts, $this->offset, $this->maxResults

        // Construct fields param
        $fields = 'fields=';
        foreach ($this->fields as $field) {
            $fields .= $field.',';
        }
        $fields = rtrim($fields, ',');

        // Construct filters param
        $filters = 'criteria=';
        foreach ($this->filters as $filter) {
            $filters .= $filter.',';
        }
        $filters = rtrim($filters, ',');

        // Construct sort param
        $sorts = 'sort=';
        foreach ($this->sorts as $field => $sort) {
            $sorts .= $field.':'.$sort.',';
        }
        $sorts = rtrim($sorts, ',');

        $queryString = $fields.'&'.$filters.'&'.$sorts
            .'&offset='.strval($this->offset)
            .'&numRecords='.strval($this->maxResults);

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

    /**
     * Execute call to API and builds processed result set.
     *
     * @return array    Enumerated array of records (each as their own
     *                  enumerated array of column values).
     */
    protected function retrieveData()
    {
        // Call API using ApiCaller to retrieve results (array of documents).
        $queryString = $this->constructQueryString();

        return $this->apiCaller->get($this->endpoint, [$queryString]);
    }

    /**
     * {@inheritdoc}
     *
     * Perform the necessary processing to create a flat
     * Record of field values.
     *
     * @param mixed $document   Document data retrieved from API
     *
     * @return array
     */
    public function transform($document)
    {
        $processedRecord = [];

        //TODO:

        foreach ($this->fields as $field) {

            // Do whatever we gotta do here (read values from
            // JSON activity document, or hand-off to callback)...

            if (is_callable($field)) {
                // Hand-off to callback to get field value.
                $value = call_user_func_array(
                    $field,
                    []
                );
            } else {

                // Perhaps...
                //$value = Arr::mustGet($field, $document);
                // or
                //$value = $document->{$field};

                // And/or whatever else...
            }

            //$processedRecord[] = $value;
        }

        return $processedRecord;
    }
}
