<?php
namespace CTLib\Component\Database;

/**
 * Standardizes exchange of database connection parameters.
 * @author Mike Turoff
 */
class DatabaseConnectionParameters
{

    /**
     * Database host.
     * @var string
     */
    protected $host;

    /**
     * Database port.
     * @var integer
     */
    protected $port;

    /**
     * Database username.
     * @var string
     */
    protected $username;

    /**
     * Database password.
     * @var string
     */
    protected $password;

    /**
     * Database name.
     * @var string
     */
    protected $dbName;

    /**
     * Database driver.
     * @var string
     */
    protected $driver;


    public function __construct()
    {
        $this->host     = null;
        $this->port     = null;
        $this->username = null;
        $this->password = null;
        $this->dbName   = null;
        $this->driver   = null;
    }

    /**
     * Sets $host.
     * @return DatabaseConnectionParameters
     */
    public function setHost($host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * Returns $host.
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Sets $port.
     * @return DatabaseConnectionParameters
     * @throws InvalidArgumentException
     */
    public function setPort($port)
    {
        if (is_int($port) == false || $port <= 0) {
            throw new \InvalidArgumentException('$port must be integer greater than 0');
        }

        $this->port = $port;
        return $this;
    }

    /**
     * Returns $port.
     * @return integer
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Sets $username.
     * @return DatabaseConnectionParameters
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Returns $username.
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Sets $password.
     * @return DatabaseConnectionParameters
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Returns $password.
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Sets $dbName.
     * @return DatabaseConnectionParameters
     */
    public function setDbName($dbName)
    {
        $this->dbName = $dbName;
        return $this;
    }

    /**
     * Returns $dbName.
     * @return string
     */
    public function getDbName()
    {
        return $this->dbName;
    }

    /**
     * Sets $driver.
     * @return DatabaseConnectionParameters
     */
    public function setDriver($driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Returns $driver.
     * @return string
     */
    public function getDriver()
    {
        return $this->driver;
    }

}
