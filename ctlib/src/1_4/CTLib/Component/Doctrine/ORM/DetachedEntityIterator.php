<?php
namespace CTLib\Component\Doctrine\ORM;


class DetachedEntityIterator implements \Iterator, \ArrayAccess
{
    

    public function __construct($entities, $entityClass)
    {
        $this->entities     = $entities;
        $this->entityClass  = $entityClass;
        $this->index        = 0;
    }

    public function current()
    {
        $entity = $this->entities[$this->index];
        return new $this->entityClass($entity);
    }

    public function key()
    {
        return $this->index;
    }

    public function next()
    {
        ++$this->index;
    }

    public function rewind()
    {
        $this->index = 0;
    }

    public function valid()
    {
        return isset($this->entities[$this->index]);
    }

    public function offsetExists($offset)
    {
        return isset($this->entities[$offset]);
    }

    public function offsetGet($offset)
    {
        if (! isset($this->entities[$offset])) {
            return null;
        }

        $entity = $this->entities[$offset];
        return new $this->entityClass($entity);
    }

    public function offsetSet($offset, $value)
    {
        $this->entities[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->entities[$offset]);
    }


}