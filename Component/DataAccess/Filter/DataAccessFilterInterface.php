<?php

namespace CTLib\Component\DataAccess\Filter;

interface DataAccessFilterInterface
{

    /**
     * Applies filter.
     *
     * @param DataAccessInterface $dataAccess
     * @param mixed $value
     *
     * @return void
     */
    public function apply($dataAccess, $value);

}
