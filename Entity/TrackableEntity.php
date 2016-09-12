<?php

namespace CTLib\Entity;


/**
 * TrackableEntity Interface
 *
 * @author David McLean <dmclean@celltrak.com>
 */
interface TrackableEntity
{
    /**
     * Start tracking of a 'new' operation.
     *
     * @return void
     */
    public function beginNew();

    /**
     * End the tracking of a 'new' operation.
     *
     * @return void
     */
    public function endNew();

    /**
     * Start the tracking of an 'edit' operation.
     *
     * @return void
     */
    public function beginEdit();

    /**
     * End the tracking of an 'edit' operation.
     *
     * @return void
     */
    public function endEdit();

    /**
     * Returns the current tracking state of the object.
     *
     * @return int
     */
    public function getTrackingState();

    /**
     * Returns an associative array of property names
     * and values of all modified properties.
     *
     * @return array
     */
    public function getModifiedProperties();
}
