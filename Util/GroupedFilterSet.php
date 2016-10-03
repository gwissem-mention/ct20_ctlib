<?php
namespace CTLib\Util;


class GroupedFilterSet implements \Iterator, \Countable
{

    protected $index;

    protected $filterGroups;

    protected $filters;

    public function __construct()
    {
        $this->index = 0;
        $this->filterGroups = [];
        $this->filters = [];
    }

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

    public function getFilters()
    {
        return array_combine($this->filterGroups, $this->filters);
    }

    public function current()
    {
        return $this->filters[$this->index];
    }

    public function key()
    {
        return $this->filterGroups[$this->index];
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
        return isset($this->filterGroups[$this->index]);
    }

    public function count()
    {
        return count($this->filterGroups);
    }



}
