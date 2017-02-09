<?php
namespace CTLib\Component\ActionLog;

/**
 * Defines user object relevant to recording action log entries.
 *
 * @author Mike Turoff
 */
interface ActionLogUserInterface
{

    /**
     * Returns user id relevant for action logger.
     * @return mixed
     */
    public function getUserIdForActionLog();

    /**
     * Returns user role relevant for action logger.
     * @return mixed
     */
    public function getRoleForActionLog();

    /**
     * Returns session id for action logger.
     * @return string
     */
    public function getSessionIdForActionLog();

    /**
     * Returns user IP address for action logger.
     * @return string
     */
    public function getIpAddressForActionLog();

    /**
     * Returns user agent for action logger.
     * @return string
     */
    public function getAgentForActionLog();

    /**
     * Returns user name for action logger.
     * @return string
     */
    public function getNameForActionLog();
}
