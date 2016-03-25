<?php

namespace CTLib\Component\DataProvider;

/**
 * Interface used to implement a data provider
 * that will format the data results retrieved
 * from a data source.
 *
 * @author David McLean <dmclean@celltrak.com>
 */
interface DataOutputInterface
{
    /**
     * @param mixed $data
     */
    public function transform($data);

}
