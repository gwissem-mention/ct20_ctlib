<?php
namespace CTLib\Component\Doctrine\ORM;

/**
 * Iterator used to convert results hydrated as array into detached entities 
 * when using Entity Repository _find* methods.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class DetachedEntityIterator implements
                                \Iterator,
                                \ArrayAccess,
                                \Countable
{
    
    /**
     * @var integer
     */
    protected $index;

    /**
     * @var array
     */
    protected $entities;

    /**
     * @var object
     */
    protected $entityMetadata;

    /**
     * @var string
     */
    protected $entityClass;

    /**
     * @var array
     */
    protected $postLoadMethods;

    /**
     * @var boolean
     */
    protected $initialized;


    /**
     * @param array $entities
     * @param object $entityMetadata
     */
    public function __construct($entities, $entityMetadata)
    {
        $this->index            = 0;
        $this->entities         = $entities;
        $this->entityMetadata   = $entityMetadata;
        $this->entityClass      = $entityMetadata->name;
        $this->postLoadMethods  = array();
        $this->initialized      = false;
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        return count($this->entities);
    }

    /**
     * Returns Entity based on current index.
     *
     * @return Entity|null
     */
    public function current()
    {
        return $this->offsetGet($this->index);
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        return $this->index;
    }

    /**
     * {@inheritDoc}
     */
    public function next()
    {
        ++$this->index;
    }

    /**
     * {@inheritDoc}
     */
    public function rewind()
    {
        $this->index = 0;
    }

    /**
     * {@inheritDoc}
     */
    public function valid()
    {
        return isset($this->entities[$this->index]);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->entities[$offset]);
    }

    /**
     * Returns Entity based on specified index.
     *
     * @param integer $offset
     * @return Entity|null
     */
    public function offsetGet($offset)
    {
        if (! $this->initialized) {
            $this->initialize();
        }

        if (! isset($this->entities[$offset])) {
            return null;
        }

        $entity = $this->entities[$offset];
        $entity = new $this->entityClass($entity);

        if (method_exists($entity, 'resetHasExplicitEffectiveTime')) {
            // This tells EffectiveEntity instances to set
            // hasExplicitEffectiveTime flag back to false so that if entity is
            // inserted, it won't attempt to use existing effective time.
            $entity->resetHasExplicitEffectiveTime();
        }

        foreach ($this->postLoadMethods as $method) {
            $entity->{$method}();
        }
        return $entity;
    }

    /**
     * {@inheritDoct}
     */
    public function offsetSet($offset, $value)
    {
        $this->entities[$offset] = $value;
    }

    /**
     * {@inheritDoct}
     */
    public function offsetUnset($offset)
    {
        unset($this->entities[$offset]);
    }

    /**
     * Initializes iterator.
     *
     * @return void
     */
    protected function initialize()
    {
        if (isset($this->entityMetadata->lifecycleCallbacks)
            && isset($this->entityMetadata->lifecycleCallbacks['postLoad'])) {
            foreach ($this
                        ->entityMetadata
                        ->lifecycleCallbacks['postLoad'] as $method) {
                $this->postLoadMethods[] = $method;
            }
        }
        unset($this->entityMetadata);
        $this->initialized = true;
    }


}
