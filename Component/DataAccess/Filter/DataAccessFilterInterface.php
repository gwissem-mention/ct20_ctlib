<?php

namespace CTLib\Component\DataAccess\Filter;

interface DataProviderFilterInterface
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
