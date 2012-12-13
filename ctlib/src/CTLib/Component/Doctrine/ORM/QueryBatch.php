<?php
namespace CTLib\Component\Doctrine\ORM;

use Doctrine\ORM\Query;
/**
  Class QueryBatch
*/
class QueryBatch implements \Iterator
{
    private $batchLimit    = null;
    private $batchNum      = 0;
    private $query         = null;
    private $queryResult   = null;
    private $isFixedOffset = null;

    public function __construct($query, $batchLimit, $isFixedOffset = false)
    {
        $this->batchLimit = $batchLimit;
        if ($query instanceof \Doctrine\ORM\QueryBuilder) {
            $this->query = clone $query->getQuery();
        }
        else if ($query instanceof \Doctrine\ORM\Query) {
            $this->query = clone $query;
        }
        else {
            throw new \Exception("query is invalid");
        }
        $this->query->setMaxResults($this->batchLimit);
    }

    public function current()
    {
        return $this->queryResult;
    }

    public function key()
    {
        return $this->batchNum;
    }

    public function next()
    {
        $this->batchNum++;
    }

    public function rewind()
    {
        $this->batchNum = 0;
    }

    public function valid()
    {
        if ($this->batchNum == 0
            || count($this->queryResult) == $this->batchLimit
        ) {
            if (!$this->isFixedOffset) {
                $this->query->setFirstResult($this->batchNum * $this->batchLimit);
            }
            
            $this->queryResult = $this->query->getResult();

            if (empty($this->queryResult)) { return false; }

            return true;
        }
        
        unset($this->queryResult);
        return false;
    }

}