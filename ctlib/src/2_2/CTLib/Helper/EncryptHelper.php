<?php
/**
 * CellTrak 2.x Project.
 *
 * @package CTLib
 */

namespace CTLib\Helper;

/**
 * Encryption helper class
 */
class EncryptHelper
{
    /**
     * Encryption algorithm to use.
     *
     * @var string
     */
    protected $algorithm;

    /**
     * Default salt value to use.
     *
     * @var string
     */
    protected $salt;

    /**
     * Constructor.
     *
     * @param string $algorithm
     * @param string $salt
     */
    public function __construct($algorithm, $salt)
    {
        $this->algorithm    = $algorithm;
        $this->salt         = $salt;
    }

    /**
     * Return the current algorithm.
     *
     * @return string
     */
    public function getAlgorithm()
    {
        return $this->algorithm;
    }

    /**
     * Set the algorithm.
     *
     * @param string $algorithm
     *
     * @return EncryptHelper
     */
    public function setAlgorithm($algorithm)
    {
        $this->algorithm = $algorithm;
        return $this;
    }

    /**
     * Return the current salt value.
     *
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * Set the salt value.
     *
     * @param string $salt
     *
     * @return EncryptHelper
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;
        return $this;
    }

    /**
     * Encrypt a string using the configured hash method and salt.
     *
     * @param string $string
     *
     * @return string
     */
    public function encrypt($string)
    {
        return hash_hmac($this->algorithm, $string, $this->salt);
    }

    /**
     * Compare an unencrypted string with it's hashed equivalent.
     *
     * @param mixed $string
     * @param mixed $hash
     *
     * @return boolean
     */
    public function match($string, $hash)
    {
        return $hash === $this->encrypt($string);
    }

}
