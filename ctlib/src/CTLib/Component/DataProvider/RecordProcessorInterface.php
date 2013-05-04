<?php
namespace CTLib\Component\DataProvider;

/**
 * Used to Process data result set returned by DataProvider.
 *
 * @author Shuang Liu <sliu@celltrak.com>
 */
interface RecordProcessorInterface
{
    /**
     * Return total number of result need for data provider
     *
     * @param QueryBuilder $queryBuilder
     * @return Integer
     *
     */    
    public function getTotal($queryBuilder);

    /**
     * Perform any process before iterating result set
     *
     * @param stdClass $model 
     * @return void 
     *
     */
    public function beforeProcessRecord($model);
    
    /**
     * Process each record in an iteration
     *
     * @param array $record each record data
     * @param stdClass $model 
     * @return void 
     *
     */
    public function processRecord($record, $model);
    
    /**
     * Get processed result set
     *
     * @param stdClass $queryConfig
     * @return void 
     *
     */
    public function getRecordResult($queryConfig);
    
    /**
     * format the result set
     *
     * @param int $total 
     * @param stdClass $model 
     * @param mixed $data processed result set
     * @return mixed any result format 
     *
     */
    public function formatResult($total, $model, $data);
}