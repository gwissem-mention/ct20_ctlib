<?php
namespace CTLib\Component\DataProvider;

use CTLib\Util\Arr;

/**
 * Facilitates processing QueryBuilder results into paginated structured data.
 *
 * @author Shuang Liu <sliu@celltrak.com>
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class DataProvider
{
    
    /**
     * @var DataProviderQueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var array
     */
    protected $modelFields;

    /**
     * @var array
     */
    protected $filterHandlers;

    /**
     * @var array
     */
    protected $defaultFilters;


    /**
     * @param DataProviderQueryBuilder $queryBuilder
     */
    public function __construct($queryBuilder)
    {
        $this->queryBuilder     = $queryBuilder;
        $this->modelFields      = array();
        $this->filterHandlers   = array();
        $this->defaultFilters   = array();
    }

    /**
     * Returns data from QueryBuilder based on Request.
     *
     * @param Request $request
     * @return array    array('data' => array, 'total' => int, 'model' => array)
     */
    public function getData($request, $fetchJoinCollection = true)
    {
        $queryConfig    = $this->getQueryConfig($request);
        $session        = $request->getSession();
        $cacheId        = $this->fromPost('cacheId', $request); 

        if ($session && $cacheId) {
            $session->set($cacheId, $queryConfig);
        }

        // fixme: there is no access to post data
        if ($this->fromPost('notify', $request)) {
            // Just needed to save the updated query config.
            return $this->getFormattedData();
        } else {
            return $this->run($queryConfig, $fetchJoinCollection);
        }
    }

    /**
     * Adds field that will have its value returned in data record.
     *
     * @param string|callable $field    If string, must be {alias}.{fieldName}.
     *                                  Callable must accept two arguments:
     *                                      $rootDataEntity, $individualValues
     * @return DataProvider
     */
    public function addModelField($field, $alias=null)
    {
        if (! is_string($field) && ! $alias) {
            throw new \Exception('$alias is required');
        }

        if (! $alias) {
            $fieldTokens = explode('.', $field);
            $alias = $fieldTokens[1];
        }

        $this->modelFields[$alias] = $field;
        return $this;
    }

    /**
     * Adds override handler for filter.
     *
     * @param string $name
     * @param callable|DataProviderFilter $handler
     *
     * @return DataProvider
     */
    public function addFilterHandler($name, $handler)
    {
        $this->filterHandlers[$name] = $handler;
        return $this;
    }

    /**
     * Adds default filter for field.
     *
     * @param string $fieldName
     * @param mixed $filter         Either default filter value or explicit
     *                              filter definition:
     *                                  array('value' => mixed, 'op' => string)
     *
     * @return DataProvider
     */
    public function addDefaultFilter($fieldName, $filter)
    {
        if (! is_array($filter) || ! isset($filter['value'])) {
            $filter = array('value' => $filter);
        }
        $this->defaultFilters[$fieldName] = $filter;
        return $this;
    }

    /**
     * Adds multiple default filter values.
     *
     * @param array $filters        array({fieldName} => {value})
     * @return DataProvider
     */
    public function addDefaultFilters($filters)
    {
        foreach ($filters as $fieldName => $filter) {
            $this->addDefaultFilter($fieldName, $filter);
        }
        return $this;
    }

    /**
     * Returns data formatted with total results and model.
     *
     * @param array $data
     * @param integer $total
     * @param array $model
     *
     * @return array
     */
    protected function getFormattedData($data=null, $total=null, $model=null)
    {
        return array(
            'data'  => $data,
            'total' => $total,
            'model' => $model
        );
    }

    /**
     * Returns query configuration from Request.
     *
     * @param Request $request
     * @return StdClass
     */
    protected function getQueryConfig($request)
    {
        $cnf = new \StdClass;
        $cnf->rowsPerPage       = $this->fromPost('rowsPerPage', $request, -1);
        $cnf->currentPage       = $this->fromPost('currentPage', $request, 1);
        $cnf->cachePages        = $this->fromPost('cachedPage', $request, 0);
        $cnf->filters           = $this->fromPost('filters', $request, array());
        $cnf->sorts             = $this->fromPost('sorts', $request, array());
        $cnf->suppressTotal     = $this->fromPost(
                                    'suppressTotal', $request, false);
        $cnf->suppressResults   = $this->fromPost(
                                    'suppressResults', $request, false);
        return $cnf;
    }

    /**
     * Returns value from POST.
     *
     * @param string $key
     * @param Request $request
     * @param mixed $default        Returned if $key not found in POST.
     *
     * @return mixed
     */
    protected function fromPost($key, $request, $default=null)
    {
        return $request->request->get($key) ?: $default;
    }

    /**
     * Runs query and returns formatted data.
     *
     * @param StdClass $queryConfig
     * @return array
     */
    protected function run($queryConfig, $fetchJoinCollection = true)
    {
        $this->applyFilters($queryConfig);

        if ($queryConfig->suppressTotal) {
            // Request doesn't require total number of results.
            $total = -1;
        } else {
            $total = $this->queryBuilder->getResultTotal();
        }

        if ($total === 0 || $queryConfig->suppressResults) {
            // When no results or request doesn't require them, return just the
            // total results found.
            return $this->getFormattedData(array(), $total);
        }

        $this->applySorts($queryConfig);
        $this->applyLimit($queryConfig);

        $data   = $this->getQueryResultSet($queryConfig, $fetchJoinCollection);
        $model  = $this->getModel($queryConfig);
        return $this->getFormattedData($data, $total, $model);
    }

    /**
     * Applies filters against QueryBuilder.
     *
     * @param StdClass $queryConfig
     * @return void
     */
    protected function applyFilters($queryConfig)
    {
        // Combine default filters with those from the request making sure we
        // give priority to the latter.
        $filters = array_merge($this->defaultFilters, $queryConfig->filters);

        foreach ($filters as $name => $filter) {
            list($value, $operator, $cacheOnly) = $this->extractFilter($filter);

            if ($cacheOnly) {
                // This filter doesn't need to be applied against the
                // QueryBuilder. It's just used to preserve the front-end filter
                // UI.
                continue;
            }

            if (isset($this->filterHandlers[$name])) {
                $this->applyFilterHandler($this->filterHandlers[$name], $value);
            } else {
                $this->applyFieldFilter($name, $value, $operator);
            }
        }
    }

    /**
     * Extracts filter into its individual components.
     *
     * @param array $filter
     * @return array
     */
    protected function extractFilter($filter)
    {
        $value      = Arr::mustGet('value', $filter);
        $operator   = Arr::get('op', $filter, 'eq');
        $cacheOnly  = Arr::get('cacheOnly', $filter, false);
        return array($value, $operator, $cacheOnly);
    }

    /**
     * Applies filter handler to QueryBuilder.
     *
     * @param DataProviderFilter|callable $handler
     * @param mixed $value
     *
     * @return void
     */
    protected function applyFilterHandler($handler, $value)
    {
        if (is_callable($handler)) {
            call_user_func($handler, $this->queryBuilder, $value);
        } elseif ($handler instanceof DataProviderFilter) {
            $handler->apply($this->queryBuilder, $value);
        } else {
            throw new \Exception("Filter handler {$handler} is invalid");
        }
    }

    /**
     * Applies standard field filter to QueryBuilder.
     *
     * @param string $fieldName
     * @param mixed $value
     * @param string $operator      Indicates comparison operation.
     *
     * @return void
     */
    protected function applyFieldFilter($fieldName, $value, $operator)
    {
        if (is_array($value)) {
            if ($operator != 'eq' && $operator != 'in') {
                throw new \Exception("Array value only supports 'eq' or 'in' operator.");
            }
            $operator = 'in';
        }

        $param = str_replace(".", "", $fieldName);
        $paramInQuery = ":{$param}";

        switch ($operator) {
            case 'eq':  // Equals.
            case 'lt':  // Less than.
            case 'lte': // Less than or equal to.
            case 'gt':  // Greater than.
            case 'gte': // Greater than or equal to.
                $expr = $this->queryBuilder->expr()->$operator(
                    $fieldName,
                    $paramInQuery
                );
                break;
            case 'in':
                $expr = $this->queryBuilder->expr()->in($fieldName, $paramInQuery);
                break;
            case 'like%':
            case '%like':
            case '%like%':
                $expr = $this->queryBuilder->expr()->like($fieldName, $paramInQuery);
                $value = str_replace("like", $value, $operator);
                break;
            case 'null':
                $expr = $this->queryBuilder->expr()->isNull($fieldName);
                break;
            case 'notnull':
                $expr = $this->queryBuilder->expr()->isNotNull($fieldName);
                break;
            default:
                throw new \Exception("Invalid operator: $operator.");
        }

        $this->queryBuilder->andWhere($expr);

        if (! in_array($operator, array('null', 'notnull'))) {
            $this->queryBuilder->setParameter($param, $value);
        }
    }

    /**
     * Applies ORDER BY conditions to QueryBuilder.
     *
     * @param StdClass $queryConfig
     * @return void
     */
    protected function applySorts($queryConfig)
    {
        if (! $queryConfig->sorts) { return; }

        foreach ($queryConfig->sorts as $sort) {
            $field = Arr::mustGet('field', $sort);
            $order = Arr::mustGet('order', $sort);
            $this->queryBuilder->addOrderBy($field, $order);
        }
    }

    /**
     * Applies LIMIT and OFFSET to QueryBuilder.
     *
     * @param StdClass $queryConfig
     * @return void
     */
    protected function applyLimit($queryConfig)
    {
        if ($queryConfig->rowsPerPage <= 0) { return; }

        $offset = ($queryConfig->currentPage - 1) * $queryConfig->rowsPerPage;
        $max = $queryConfig->rowsPerPage
                + $queryConfig->cachePages * $queryConfig->rowsPerPage;

        $this->queryBuilder->setFirstResult($offset);
        $this->queryBuilder->setMaxResults($max);
    }

    /**
     * Execute query and builds processed result set.
     *
     * @param StdClass $queryConfig
     *
     * @return array    Enumerated array of records (each as their own
     *                  enumerated array of column values).
     */
    protected function getQueryResultSet($queryConfig, $fetchJoinCollection = true)
    {
        $rawResultSet = $this->queryBuilder->getPaginatedResult($fetchJoinCollection);

        if (! $rawResultSet) {
            return array();
        }

        $queryMetaMap = $this->queryBuilder->getQueryMetaMap();
        $processedResultSet = array();
        foreach ($rawResultSet as $rawRecord) {
            $record = $this->processResultRecord(
                $rawRecord,
                $queryMetaMap,
                $queryConfig
            );
            // record can be ignored by returning null from
            // function processResultRecord
            if (isset($record)) {
                $processedResultSet[] = $record;
            }
        }
        return $processedResultSet;
    }

    /**
     * Processes raw result record into one used in response.
     *
     * @param mixed $rawRecord
     * @param QueryMetaMap $queryMetaMap
     * @param StdClass $queryConfig
     *
     * @return array
     */
    protected function processResultRecord($rawRecord, $queryMetaMap,
        $queryConfig)
    {
        // Extract two possible components from raw record:
        //  1. $rootDataEntity      Returned if we selected at least one
        //                          entity.
        //  2. $individualValues    Returned if we selected at least one
        //                          individual field value.
        list(
            $rootDataEntity,
            $individualValues   ) = $this->extractResultRecord($rawRecord);

        $processedRecord = array();
        
        foreach ($this->modelFields as $field) {
            $processedRecord[] = $this->getFieldValue(
                $field,
                $rootDataEntity,
                $individualValues,
                $queryMetaMap
            );
        }

        return $processedRecord;
    }

    /**
     * Extracts raw result record components.
     *
     * @param mixed $record
     * @return array
     */
    protected function extractResultRecord($record)
    {
        if (is_object($record)) {
            $rootDataEntity = $record;
            $individualValues = array();
        } else {
            $rootDataEntity = isset($record[0]) ? array_shift($record) : null;
            $individualValues = $record;
        }
        return array($rootDataEntity, $individualValues);
    }

    /**
     * Returns model aliases used.
     *
     * @param StdClass $queryConfig
     * @return array
     */
    protected function getModel($queryConfig)
    {
        return array_keys($this->modelFields);
    }

    /**
     * Retrieves value for field.
     *
     * @param mixed $field                 Field name or callable.
     * @param Entity $rootDataEntity
     * @param array $individualValues
     * @param QueryMetaMap $queryMetaMap
     *
     * @return mixed
     */
    protected function getFieldValue($field, $rootDataEntity, $individualValues,
        $queryMetaMap)
    {
        if (is_callable($field)) {
            // Hand-off to callback to get field value.
            return call_user_func($field, $rootDataEntity, $individualValues);
        }

        if (! strpos($field, '.')) {
            // Field's value should be found within individual values.
            return Arr::mustGet($field, $individualValues);
        }

        // Field's value should be found in data entity.
        list(
            $entityAlias,
            $fieldName,
            $lazyLoadedAssociations ) = $this->extractField($field);

        // Get entity's query metadata so we can correctly retrieve it in our
        // potentially nested data entity.
        $entityMeta = $queryMetaMap->getEntity($entityAlias);

        if (! $entityMeta) {
            throw new \Exception("Entity meta not found for alias: $entityAlias.");
        }

        $dataEntity = $this->getDataEntity($rootDataEntity, $entityMeta);

        // Iterate through lazy-loaded associations to get the desired data
        // entity.
        foreach ($lazyLoadedAssociations as $associationName) {
            $dataEntity = $dataEntity->{"get" . ucfirst($associationName)}();
        }

        // Finally, return field's value.
        return $dataEntity->{"get" . ucfirst($fieldName)}();
    }

    /**
     * Extracts field's source into its components.
     *
     * @param string $field
     * @return array    array($entityAlias, $fieldName, $lazyLoadedAssociations)
     */
    protected function extractField($field)
    {
        $fieldTokens            = explode('.', $field);
        $entityAlias            = array_shift($fieldTokens);
        $fieldName              = array_shift($fieldTokens);
        $lazyLoadedAssociations = $fieldTokens;

        return array($entityAlias, $fieldName, $lazyLoadedAssociations);
    }

    /**
     * Return's data entity for the entity defined in $entityMeta.
     *
     * @param Entity $rootDataEntity
     * @param StdClass $entityMeta
     *
     * @return Entity
     */
    protected function getDataEntity($rootDataEntity, $entityMeta)
    {
        if (! $entityMeta->route) { return $rootDataEntity; }

        foreach ($entityMeta->route as $step) {
            $associationName = Arr::mustGet('associationName', $step);
            $dataEntity = $rootDataEntity->{"get" . ucfirst($associationName)}();

            if (method_exists($dataEntity, 'first')) {
                if (count($dataEntity) > 1) {
                    throw new \Exception("Cannot support collections with multiple entries");
                }
                $dataEntity = $dataEntity->first();
            }
        }
        return $dataEntity;
    }

    /**
     * Get QueryBuilder
     *
     * @return QueryBuilder
     *
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }
}