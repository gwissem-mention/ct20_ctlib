<?php

namespace CTLib\Component\ActionLog;

/**
 * Defines entity 'extra' data relevant to recording
 * action log entries.
 *
 * @author David McLean
 */
interface ActionLogEntityAttribute
{
    /**
     * Returns various properties relevant for action logger.
     *
     * @return array   $key => $value
     */
    public function getAttributesForActionLog();
}
