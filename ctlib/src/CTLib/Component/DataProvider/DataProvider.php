<?php
namespace CTLib\Component\DataProvider;

use CTLib\Util\Arr,
    CTLib\Component\Doctrine\ORM\QueryBatch;

/**
 * Facilitates processing QueryBuilder results into paginated structured data.
 *
 * @author Shuang Liu <sliu@celltrak.com>
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class DataProvider
{
    const REQUEST_TYPE_NOTIFY = "notify";
    const REQUEST_TYPE_JSON   = "json";
    const REQUEST_TYPE_CSV    = "csv";
    const REQUEST_TYPE_PDF    = "pdf";

    const BATCH_RECORD_SIZE = 50;


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
     * @var Router
     */    
    protected $router;

    /**
     * Provide a shared variable for all model callback function
     * @var mixed 
     */    
    protected $rowContext;

    /**
     * determine if paginator is fetch Joined 
     * detail see: http://docs.doctrine-project.org/en/latest/tutorials/pagination.html

     * @var boolean
     */    
    protected $fetchJoinCollection;

    /**
     * @param DataProviderQueryBuilder $queryBuilder
     */
    public function __construct($queryBuilder, $fetchJoinCollection = true)
    {
        $this->queryBuilder     = $queryBuilder;
        $this->modelFields      = array();
        $this->filterHandlers   = array();
        $this->defaultFilters   = array();
        $this->rowContext       = array();
        $this->fetchJoinCollection = $fetchJoinCollection;
    }

    /**
     * Returns data from QueryBuilder based on Request.
     *
     * @param Request $request
     * @return array    array('data' => array, 'total' => int, 'model' => array)
     */
    public function getData($request)
    {
        $queryConfig    = $this->getQueryConfig($request);
        $session        = $request->getSession();
        $cacheId        = $this->fromPost('cacheId', $request); 

        if ($session && $cacheId) {
            $session->set($cacheId, $queryConfig);
        }

        $requestType = $this->getRequestType($request);
        if ($requestType == static::REQUEST_TYPE_NOTIFY) {
            return null;
        }

        $recordProcessor = $this->getRecordProcessor($requestType);

        return $this->run($recordProcessor, $queryConfig);
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
     * Get recordset ajax request type. It could be any of 
     * these: REQUEST_TYPE_NOTIFY, REQUEST_TYPE_JSON,
     * REQUEST_TYPE_CSV, REQUEST_TYPE_PDF
     * 
     * @param Request $request 
     * @return string
     *
     */
    public function getRequestType($request)
    {
        $requestType = $this->fromPost('requestType', $request);
        if ($requestType != static::REQUEST_TYPE_NOTIFY
            && $requestType != static::REQUEST_TYPE_JSON
            && $requestType != static::REQUEST_TYPE_CSV
            && $requestType != static::REQUEST_TYPE_PDF
        ) {
            throw new \Exception("request type is not valid");
        }
        
        return $requestType;
    }

    /**
     * Get corresponding RecordProcessor by matching request type
     *
     * @param string $requestType
     * @return RecordProcessorInterface
     *
     */
    protected function getRecordProcessor($requestType)
    {
        switch ($requestType) {
            case static::REQUEST_TYPE_JSON:
                return new JsonRecordProcessor($this->fetchJoinCollection);

            case static::REQUEST_TYPE_CSV:
                return new CsvRecordProcessor();

            case static::REQUEST_TYPE_PDF:

            default:
                throw new \Exception("request type is not supported");
        }

        return null;
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
        list(
            $requestedFields,
            $requestedAliases
        ) = $this->seperateFieldsAndAliases($this->fromPost("fields", $request));

        $cnf->fields   = $requestedFields;
        $cnf->aliases  = $requestedAliases;

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
    protected function run(RecordProcessorInterface $recordProcessor, $queryConfig)
    {
        $this->applyFilters($queryConfig);

        $model = $this->getModel($queryConfig);

        if ($queryConfig->suppressTotal) {
            $total = -1;
        }
        else {
            $total = $recordProcessor->getTotal($this->queryBuilder);
        }

        if ($total === 0) {
            // When no results or request doesn't require them, return just the
            // total results found.
            return $recordProcessor->formatResult($total, $model, array());
        }

        $this->applySorts($queryConfig);

        if ($queryConfig->rowsPerPage > 0) {
            $this->applyLimit($queryConfig);
        }

        $data = $this->getQueryResultSet($recordProcessor, $queryConfig, $model);

        return $recordProcessor->formatResult($total, $model, $data);
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
     * There are two ways to iterator records. If rowsPerPage is set,
     * loop thru using paginated quey result. Otherwise loop thru records
     * using batch query result to save query time
     *
     * @param RecordProcessorInterface $recordProcessor record processor
     * @param StdClass $queryConfig
     * @param stdClass $model
     * 
     * @return array    Enumerated array of records (each as their own
     *                  enumerated array of column values).
     */
    protected function getQueryResultSet(RecordProcessorInterface $recordProcessor,
        $queryConfig, $model)
    {
        $recordProcessor->beforeProcessRecord($model);
        $queryMetaMap = $this->queryBuilder->getQueryMetaMap();

        // loop thru paginated iterator
        if ($queryConfig->rowsPerPage > 0) {
            $iterator = $this->queryBuilder
                ->getPaginatedResult($this->fetchJoinCollection);
            foreach ($iterator as $rawRecord) {
                $this->iterateRecord(
                    $recordProcessor,
                    $rawRecord,
                    $queryMetaMap,
                    $queryConfig,
                    $model
                );
            }
        }
        // loop thru batch result
        else {
            $this->queryBuilder
                ->getEntityManager()
                ->getConnection()
                ->getConfiguration()
                ->setSqlLogger(null);

            $batches = new QueryBatch(
                $this->queryBuilder,
                static::BATCH_RECORD_SIZE
            );

            foreach ($batches as $batch) {
                foreach ($batch as $rawRecord) {
                    $this->iterateRecord(
                        $recordProcessor,
                        $rawRecord,
                        $queryMetaMap,
                        $queryConfig,
                        $model
                    );
                }
            }
        }

        return $recordProcessor->getRecordResult($queryConfig);
    }

    /**
     * Perform iteration on each Record
     *
     * @param RecordProcessorInterface $recordProcessor record processor
     * @param mixed $rawRecord record data retrieved by doctrine
     * @param QueryMetaMap $queryMetaMap Query meta data
     * @param stdClass $queryConfig 
     * @param stdClass $model
     * @return void
     *
     */
    protected function iterateRecord(RecordProcessorInterface $recordProcessor,
        $rawRecord, $queryMetaMap, $queryConfig, $model)
    {
        // reset row contentx variable for each row.
        $this->rowContext = array();

        $record = $this->processResultRecord(
            $rawRecord,
            $queryMetaMap,
            $queryConfig,
            $model
        );

        // record can be ignored by returning null
        if (isset($record)) {
            $recordProcessor->processRecord($record, $model);
        }

        //clear row context variable for each row.
        unset($this->rowContext);
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
        $queryConfig, $model)
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

        foreach ($model->fields as $field) {
            // convert it into query field, which is configured
            // using addModel function
            $sourceField = Arr::get($field, $this->modelFields);
            if (! $sourceField) { continue; }
            
            $processedRecord[] = $this->getFieldValue(
                $sourceField,
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
     * Returns models that are used in query builder and also fitered 
     * in javascript.
     *
     * @param StdClass $queryConfig
     * @return StdClass that contains fields and aliases
     */
    protected function getModel($queryConfig)
    {
        $model = new \StdClass;
        //source fields are ones that are added by AddModelField
        $sourceFields  = array_keys($this->modelFields);

        // if not configured fields in javascript
        // return all available fields
        if (empty($queryConfig->fields)) {
            $model->fields  = $sourceFields;
            $model->aliases = $sourceFields;
            return $model;
        }

        // if configured fields in javascript
        // only get fields that are configured in javascript
        $model->fields  = array();
        $model->aliases = array();
        foreach ($queryConfig->fields as $i => $field) {
            if (in_array($field, $sourceFields)) {
                $model->fields[]  = $field;
                $model->aliases[] = $queryConfig->aliases[$i];
            }
        }

        return $model;
    }

    /**
     * Retrieves value for field.
     *
     * @param string $field query field name or callable.
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
            return call_user_func_array(
                $field,
                array(
                    $rootDataEntity,
                    $individualValues,
                    &$this->rowContext
                )
            );
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

//    /**
//     * Get non-paginated data, and wrap it around with QueryBatch
//     *
//     * @param stdClass $queryConfig
//     * @param boolean $fetchJoinCollection
//     * @return QueryBatch
//     *
//     */
//    protected function getFullDataBatch($queryConfig, $fetchJoinCollection = true)
//    {
//        $this->applyFilters($queryConfig);
//        $this->applySorts($queryConfig);
//
//        // turn off logging to save memory
//        $this->queryBuilder
//            ->getEntityManager()
//            ->getConnection()
//            ->getConfiguration()
//            ->setSqlLogger(null);
//
//        $batches = new QueryBatch($this->queryBuilder->getQuery(), 50);
//        return $batches;
//    }

    /**
     * Save result data into temporary file
     *
     * @param stdClass $queryConfig
     * @param boolean $fetchJoinCollection
     * @return string temp file name
     *
     */
    protected function saveToTempFile($queryConfig, $fetchJoinCollection)
    {
        $batches = $this->getFullDataBatch($queryConfig, $fetchJoinCollection);
        $this->getQueryResultSet(
            $batches,
            $queryConfig,
            $fetchJoinCollection
        );
        if ($queryConfig->downloadType == "csv") {
            return $this->saveBatchToCsv(
                $batches,
                $queryConfig,
                $fetchJoinCollection
            );
        }
        // to be supported
        elseif ($queryConfig->downloadType == "pdf") {
            
        }
        
        return null;
    }

    /**
     * Save batch data result into CSV file
     *
     * @param QueryBatch $batches batch result
     * @param stdClass $queryConfig container of query configs
     * @param boolean $fetchJoinCollection
     * @param Request $request request
     * @return string return temp file name
     *
     */
    protected function saveBatchToCsv($batches, $queryConfig, $fetchJoinCollection)
    {
        $queryMetaMap = $this->queryBuilder->getQueryMetaMap();
        $tmpFileName  = tempnam(sys_get_temp_dir(), "rst");
        $tmpHandle    = fopen($tmpFileName, "w");
        $modelAlias   = $this->getModelAlias($queryConfig);

        fputcsv($tmpHandle, $modelAlias);

        foreach ($batches as $batch) {
            
            foreach ($batch as $record) {
                $record = $this->processResultRecord(
                    $record,
                    $queryMetaMap,
                    $queryConfig
                );

                // convert array in each record into string
                $record = array_map(
                    function ($item) {
                        if (!is_array($item)) {
                            return $item;
                        }
                        return implode("|", $item);
                    },
                    $record
                );
                fputcsv($tmpHandle, $record);
            }
        }

        fclose($tmpHandle);

        return $tmpFileName;
    }

    /**
     * Get requested fields and alias that are configured
     * in the javascript
     *
     * @param Request $request
     * @return array array of fields and aliases
     *
     */
    protected function seperateFieldsAndAliases($fieldsConfig)
    {
        if (empty($fieldsConfig) || !is_array($fieldsConfig)) {
            return array(null, null);
        }

        $fields = $aliases = array();
        
        foreach ($fieldsConfig as $config) {
            $fields[] = $config[0];
            $aliases[] = Arr::get(1, $config, $config[0]);
        }
        
        return array($fields, $aliases);
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