<?php
namespace CTLib\Util;

/**
 * Collection of grouped filters.
 * @author Mike Turoff
 */
class GroupedFilterSet implements \Iterator, \Countable, \JsonSerializable
{
    /**
     * Maintains iterator position.
     * @var integer
     */
    protected $index;

    /**
     * Set of unique filter groups.
     * @var array
     */
    protected $filterGroups;

    /**
     * Set of filter sets (one per group).
     * @var array
     */
    protected $filters;

    public function __construct()
    {
        $this->index = 0;
        $this->filterGroups = [];
        $this->filters = [];
    }

    /**
     * Adds filters for specified group. Will append filters if group already
     * exists.
     * @param mixed $filterGroup
     * @param mixed $filters Can add individual or multiple.
     * @return GroupedFilterSet
     */
    public function addFilters($filterGroup, $filters)
    {
        $filters = (array) $filters;
        $index = array_search($filterGroup, $this->filterGroups);

        if ($index === false) {
            $this->filterGroups[] = $filterGroup;
            $this->filters[] = $filters;
        } else {
            $filters = array_merge($this->filters[$index], $filters);
            $this->filters[$index] = $filters;
        }
        return $this;
    }

    /**
     * Returns grouped filters as array.
     * @return array [filterGroupId => [filterId, ...], ...]
     */
    public function getGroupedFilters()
    {
        return array_combine($this->filterGroups, $this->filters);
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        return $this->filters[$this->index];
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        return $this->filterGroups[$this->index];
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
        return isset($this->filterGroups[$this->index]);
    }

    /**
     * Returns the number of unique filter groups in collection.
     * @return integer
     */
    public function count()
    {
        return count($this->filterGroups);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        return $this->getGroupedFilters();
    }

}
