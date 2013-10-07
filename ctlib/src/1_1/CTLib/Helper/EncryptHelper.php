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
    protected $algo = "sha256";

    /**
     * Default salt value to use.
     *
     * @var string
     */
    protected $salt = 'fA(PVdy|/>*dFJm{.n6a<,bDX@p/JBi!)bk{hK3d730JUMcgDAitAk1U0E9a32.SzPFLPct7e5s1l,Mp)ld6e8ZohXX!B,:#p$vbO@?/Aw{h}&BkfY}oq2{6SB7XM!>Ko>XCmhCG:albg|)t?S/0KC#@.X)j.(/QE|V9zUwA*y29VhP|uP!wplr9Lhmlv/nZU3ur/8S&RI<XMR3f:EQWOlinI75?,vB*3IX7,d^K}Cnb(n}1VfWBJX$_/ADg';

    /**
     * Constructor.
     *
     * @param string $salt
     */
    public function __construct($salt=false)
    {
        if ($salt) {
            $this->salt = $salt;
        }
    }

    /**
     * Return the current algorithm.
     *
     * @return string
     */
    public function getAlgo()
    {
        return $this->algo;
    }

    /**
     * Set the algorithm.
     *
     * @param string $algorithm
     *
     * @return EncryptHelper
     */
    public function setAlgo($algorithm)
    {
        $this->algo = $algorithm;
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
        return hash_hmac($this->algo, $string, $this->salt);
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
