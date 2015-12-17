<?php
namespace CTLib\Component\DataProvider;

use CTLib\Component\HttpFoundation\JsonResponse;

/**
 * class to output paginated json data for data provider
 *
 * @author Shuang Liu <sliu@celltrak.com>
 */
class JsonRecordProcessor implements RecordProcessorInterface
{
    /**
     * determine if paginator is fetch Joined 
     * detail see: http://docs.doctrine-project.org/en/latest/tutorials/pagination.html
     *
     * @var boolean 
     */    
    protected $fetchJoinCollection;

    /**
     * array to store data result
     *
     * @var array
     */
    protected $result;

    public function __construct($fetchJoinCollection = true)
    {
        $this->fetchJoinCollection = $fetchJoinCollection;
        $this->result              = array();
    }

    /**
     * {@inheritdoc}
     */
    public function getTotal($queryBuilder)
    {
        return $queryBuilder->getResultTotal();
    }
    
    /**
     * {@inheritdoc}
     */
    public function beforeProcessRecord($model)
    {
        $this->result = array();
    }
    
    /**
     * {@inheritdoc}
     */
    public function processRecord($raw, $record, $model)
    {
        $this->result[] = $record;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecordResult($queryConfig)
    {
        return $this->result;
    }
    
    /**
     * {@inheritdoc}
     */
    public function formatResult($total, $model, $data)
    {
        return array(
            'data'  => $data,
            'total' => $total,
            'model' => $model->aliases
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDataResponse($data)
    {
        return new JsonResponse($data);
    }
}