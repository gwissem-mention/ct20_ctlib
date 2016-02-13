<?php
namespace CTLib\Exception;

/**
 * Database exception.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class DbException extends \Exception
{
    
    const DUPLICATE_KEY_VIOLATION   = 1062;


    /**
     * Indicates whether exception is a database duplicate key violation.
     *
     * @param string|null $keyName  If passed, will indicate whether that
     *                              specific key was violated. If null, will
     *                              indicate if general duplicate key violation.
     * @return boolean
     */
    public function isDuplicateKeyViolation($keyName=null)
    {
        if ($this->getCode() != self::DUPLICATE_KEY_VIOLATION) { return false; }
        if (! $keyName) { return true; }
        return strpos($this->getMessage(), $keyName) !== false;
    }
    
    /**
     * Shortcut to create DbException from PDOException.
     *
     * @param PDOException $exception
     * @return DbException
     */
    public static function createFromPdoException($exception)
    {
        list($sqlCode, $driverCode, $driverMessage) = $exception->errorInfo;
        return new self($driverMessage, (int) $driverCode);
    }


}