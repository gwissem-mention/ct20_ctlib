<?php
namespace CTLib\Helper;

/**
 * Encrypt
 * Encryption helper class
 **/
class EncryptHelper
{
    protected $algo = "sha256";
    protected $salt = 'fA(PVdy|/>*dFJm{.n6a<,bDX@p/JBi!)bk{hK3d730JUMcgDAitAk1U0E9a32.SzPFLPct7e5s1l,Mp)ld6e8ZohXX!B,:#p$vbO@?/Aw{h}&BkfY}oq2{6SB7XM!>Ko>XCmhCG:albg|)t?S/0KC#@.X)j.(/QE|V9zUwA*y29VhP|uP!wplr9Lhmlv/nZU3ur/8S&RI<XMR3f:EQWOlinI75?,vB*3IX7,d^K}Cnb(n}1VfWBJX$_/ADg';

    public function __construct($salt=false) {
        if ($salt) $this->salt = $salt;
    }

    public function getAlgo()
    {
        return $this->algo;
    }

    public function setAlgo($algo)
    {
        $this->algo = $algo;
        return $this;
    }

    public function getSalt()
    {
        return $this->salt;
    }

    public function setSalt($salt)
    {
        $this->salt = $salt;
        return $this;
    }

    public function encrypt($str)
    {
        return hash_hmac($this->algo, $str, $this->salt);
    }

    public function match($str, $hash)
    {
        return $hash == $this->encrypt($str);
    }

}
?>
