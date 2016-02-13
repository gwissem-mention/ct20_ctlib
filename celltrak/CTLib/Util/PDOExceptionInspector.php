<?php
namespace CTLib\Util;

use Doctrine\DBAL\DBALException;


/**
 * Utility class to facilitate inspecting PDOExceptions.
 *
 * @author Mike Turoff
 */
class PDOExceptionInspector
{
    
    /**
     * Exception being inspected.
     * @var PDOException
     */
    protected $pdoException;


    /**
     * @param \PDOException $pdoException Exception to insepect.
     */
    public function __construct(\PDOException $pdoException)
    {
        $this->pdoException = $pdoException;
    }

    /**
     * Indicates whether exception was triggered due to transaction deadlock.
     * @return boolean
     */
    public function isDeadlock()
    {
        $dbErrorCode    = $this->getSQLState();
        $lockErrorCodes = ['40001', 'HY000'];

        return in_array($dbErrorCode, $lockErrorCodes); 
    }

    /**
     * Indicates whether exception was triggered due to duplicate key violation.
     * @return boolean 
     */
    public function isDuplicateKey()
    {
        $dbErrorCode        = $this->getSQLState();
        $dupKeyErrorCodes   = ['23000'];

        return in_array($dbErrorCode, $dupKeyErrorCodes); 
    }

    /**
     * Returns SQL state (error) defined by the exception.
     * @return string 
     */
    public function getSQLState()
    {
        return (string) $this->pdoException->getCode();
    }

}