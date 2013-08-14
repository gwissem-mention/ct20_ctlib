<?php
namespace CTLib\Component\DataProvider;

use CTLib\Util\Arr,
    Symfony\Component\HttpFoundation\Response,
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
     * Stores mapping from request type to record processor
     * @var mixed 
     */
    protected $recordProcessorMap;

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
        $this->queryBuilder        = $queryBuilder;
        $this->modelFields         = array();
        $this->filterHandlers      = array();
        $this->defaultFilters      = array();
        $this->rowContext          = array();
        $this->fetchJoinCollection = $fetchJoinCollection;
        $this->recordProcessorMap  = array();
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
        $cacheId        = $request->get('cacheId'); 

        if ($session && $cacheId) {
            $session->set($cacheId, $queryConfig);
        }

        $requestType = $this->getRequestType($request);

        $recordProcessor = $this->getRecordProcessor($requestType);

        if (!$recordProcessor) { return null; }

        return $this->run($recordProcessor, $queryConfig);
    }

    /**
     * Returns Response that contains result data
     *
     * @param Request $request
     * @return Response
     *
     */
    public function getDataResponse($request)
    {
        $requestType = $this->getRequestType($request);
        $recordProcessor = $this->getRecordProcessor($requestType);
        if (!$recordProcessor) {
            return new Response;
        }
        return $recordProcessor->getDataResponse($this->getData($request));
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
        $requestType = $request->get("requestType");
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
     * Register record process based on type
     *
     * @param string $requestType 
     * @param RecordProcessorInterface $recordProcessor 
     * @return void 
     *
     */
    public function registerRecordProcessor($requestType, RecordProcessorInterface $recordProcessor)
    {
        if ($requestType != static::REQUEST_TYPE_NOTIFY
            && $requestType != static::REQUEST_TYPE_JSON
            && $requestType != static::REQUEST_TYPE_CSV
            && $requestType != static::REQUEST_TYPE_PDF
        ) {
            throw new \Exception(
                "The request type " . $requestType . " is invalid. " . 
                empty($this->recordProcessorMap) ? "" : serialize($this->recordProcessorMap)
            );
        }

        $this->recordProcessorMap[$requestType] = $recordProcessor;
    }

    /**
     * Register Record Processors
     *
     * @param array $map map between types and processors
     * @return void
     *
     */
    public function registerRecordProcessors(array $map)
    {
        foreach ($map as $type => $processor) {
            $this->registerRecordProcessor($type, $processor);
        }
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
        if (!array_key_exists($requestType, $this->recordProcessorMap)) {
            switch ($requestType) {
                case static::REQUEST_TYPE_NOTIFY:
                    $this->recordProcessorMap[static::REQUEST_TYPE_NOTIFY] = null;
                    break;
                case static::REQUEST_TYPE_JSON:
                    $this->recordProcessorMap[static::REQUEST_TYPE_JSON]
                        = new JsonRecordProcessor($this->fetchJoinCollection);
                    break;
                default:
                    $this->recordProcessorMap[$requestType] = null;
            }
        }

        return Arr::get(
            $requestType,
            $this->recordProcessorMap
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
        $cnf->rowsPerPage       = $request->get('rowsPerPage', -1);
        $cnf->currentPage       = $request->get('currentPage', 1);
        $cnf->cachePages        = $request->get('cachedPage', 0);
        $cnf->filters           = $request->get('filters', array());
        $cnf->sorts             = $request->get('sorts', array());
        $cnf->suppressTotal     = $request->get('suppressTotal', false);
        $cnf->suppressResults   = $request->get('suppressResults', false);
        list(
            $requestedFields,
            $requestedAliases
        ) = $this->seperateFieldsAndAliases($request->get("fields"));

        $cnf->fields   = $requestedFields;
        $cnf->aliases  = $requestedAliases;

        return $cnf;
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
            if (! in_array($operator, array('eq', 'in', "notIn"))) {
                throw new \Exception("Array value only supports 'eq' or 'in' operator.");
            }
            if ($operator == 'eq') { $operator = 'in'; }
        }

        $param = str_replace(".", "", $fieldName);
        $paramInQuery = ":{$param}";
        $expr = $this->queryBuilder->expr();

        switch ($operator) {
            case 'eq':  // Equals.
            case 'neq':  // Equals.
            case 'lt':  // Less than.
            case 'lte': // Less than or equal to.
            case 'gt':  // Greater than.
            case 'gte': // Greater than or equal to.
                $expr = $expr->$operator(
                    $fieldName,
                    $paramInQuery
                );
                break;
            case 'in':
                $expr = $expr->in($fieldName, $paramInQuery);
                break;
            case 'notIn':
                $expr = $expr->notIn($fieldName, $paramInQuery);
                break;
            case 'like%':
            case '%like':
            case '%like%':
                $expr = $expr->like($fieldName, $paramInQuery);
                $value = str_replace("like", $value, $operator);
                break;
            case 'null':
                $expr = $expr->isNull($fieldName);
                break;
            case 'notnull':
                $expr = $expr->isNotNull($fieldName);
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
            $recordProcessor->processRecord($rawRecord, $record, $model);
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
            if (is_array($config)) {
                $fields[] = $config[0];
                $aliases[] = Arr::get(1, $config, $config[0]);
            }
            elseif (is_string($config)) {
                $fields[] = $config;
                $aliases[] = $config;
            }
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