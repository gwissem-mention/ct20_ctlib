<?php
namespace CTLib\Component\DataAccess\QueryParams;

use CTLib\Component\DataAccess\DataAccessInterface;

class FilterConfig
{
    //Constants for dealing with pagination
    const MAX_RESULTS       = 'limit';
    const OFFSET            = 'offset';
    const ORDER_BY          = 'sort';
    const ORDER_DIRECTION   = 'sortDir';

    /**
     * @var array $params
     */
    private $params = [];

    /**
     * @var int $maxResults
     */
    private $maxResults = 0;

    /**
     * @var int $offset
     */
    private $offset = 0;

    /**
     * @var array $sorts
     */
    private $sorts = [];

    /**
     * Creates another instance of the Param class and then adds it to the array
     * of params
     *
     * @param mixed Param or string
     * @return FilterConfig
     */
    public function addParam($param)
    {
        if ($param instanceof Param) {
            $this->params[] = $param;
        } else {
            $this->params[] = new Param($param);
        }

        return $this;
    }

    /**
     * Utilizing fall through to run the set methods on the current param
     *
     * @return FilterConfig
     */
    public function __call($method, $arguments)
    {
        call_user_func_array([end($this->params), $method], $arguments);
        return $this;
    }

    /**
     * Filters the built filter by the supplied query params
     *
     * @param array $queryParams
     */
    public function filterBy($queryParams)
    {
        $result = new self();

        if (isset($queryParams[self::MAX_RESULTS])
            && $queryParams[self::MAX_RESULTS] > 0) {
            $result->setMaxResults($queryParams[self::MAX_RESULTS]);
        }

        if (isset($queryParams[self::OFFSET])) {
            $result->setOffset($queryParams[self::OFFSET]);
        }

        if (isset($queryParams[self::ORDER_BY])) {
            $direction = 'ASC';
            if (isset($queryParams[self::ORDER_DIRECTION])) {
                $direction = $queryParams[self::ORDER_DIRECTION];
            }

            $result->setSort($queryParams[self::ORDER_BY], $direction);
        }

        foreach ($this->params as $param) {
            if ($param->isRequired() && !isset($queryParams[$param->name])) {
                throw new \Exception("{$param->name} is a required query parameter");
            }

            if (isset($queryParams[$param->name])) {
                $param->setValue($queryParams[$param->name]);
                $result->addParam($param);
            }
        }

        return $result;
    }

    /**
     * Helper method to invoke proper output call
     *
     * @return array filters for dataProvider
     */
    public function getFiltersConfig()
    {
        return Output::paramsToDataProviderFilters($this->outputParams());
    }

    /**
     * Returns only the params that aren't ignored
     */
    private function outputParams()
    {
        return array_filter($this->params, function ($param) {
            return !$param->isIgnored();
        });
    }

    /**
     * Helper method to add all valid points into a DataAccessInterface class
     *
     * @param object DataAccessInterface
     */
    public function assimilateAccess(DataAccessInterface $dataAccess)
    {
        $dataAccess
            ->addFilters($this->getFiltersConfig())
            ->setMaxResults($this->getMaxResults())
            ->setOffset($this->getOffset());

        foreach ($this->sorts as $sort) {
            $dataAccess->addSort($sort['column'], $sort['direction']);
        }
    }

    /**
     * setter for offset variable
     *
     * @param int $int
     * @return QueryBuilderFilterConfig
     */
    public function setOffset($int)
    {
        $this->offset = $int;
    }

    /**
     * setter for maxResults variable
     *
     * @param int $int
     * @return QueryBuilderFilterConfig
     */
    public function setMaxResults($int)
    {
        $this->maxResults = $int;
    }

    /**
     * unifromly sets the sorts to be used on a data access class
     *
     * @param string $colum
     * @param string $direction
     */
    public function setSort($column, $direction)
    {
        $this->sorts[] = [
            'column'    => $column,
            'direction' => $direction
        ];
    }

    /**
     * Getter for maxResults variable
     *
     * @return int $maxResults
     */
    public function getMaxResults()
    {
        return $this->maxResults;
    }

    /**
     * Getter for offset variable
     *
     * @return int $offset
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Retrieves value for a set param or null if the value is not found
     *
     * @param string $paramName
     * @return mixed value of the requested param
     */
    public function getParamValue($paramName)
    {
        foreach ($this->params as $param) {
            if ($param->name == $paramName) {
                return $param->value;
            }
        }
        return null;
    }
}

