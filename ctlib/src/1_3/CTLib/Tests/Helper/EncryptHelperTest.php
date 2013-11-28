<?php

namespace CTLib\Tests\Helper;

use CTLib\Helper\EncryptHelper;

/**
 * SiteHelper tests.
 *
 * @author K. Gustavson <kgustavson@celltrak.com>
 */
class EncryptHelperTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Set up tests.
     *
     * @return void
     */
    public function setUp()
    {
        $this->helper = new EncryptHelper;
    }


    /**
     * Should return the default algorythm.
     *
     * @test
     * @group unit
     * @return void
     */
    public function shouldGetDefaultAlgo()
    {
        $this->assertEquals('sha256', $this->helper->getAlgo());
    }

    /**
     * Should set the algorthym.
     *
     * @test
     * @group unit
     * @return void
     */
    public function shouldSetAlgo()
    {
        $this->helper->setAlgo('md5');
        $this->assertEquals('md5', $this->helper->getAlgo());
    }

    /**
     * Should return the default salt value.
     *
     * @test
     * @group unit
     * @return void
     */
    public function shouldGetDefaultSalt()
    {
        $salt = 'fA(PVdy|/>*dFJm{.n6a<,bDX@p/JBi!)bk{hK3d730JUMcgDAitAk1U0E9a32.SzPFLPct7e5s1l,Mp)ld6e8ZohXX!B,:#p$vbO@?/Aw{h}&BkfY}oq2{6SB7XM!>Ko>XCmhCG:albg|)t?S/0KC#@.X)j.(/QE|V9zUwA*y29VhP|uP!wplr9Lhmlv/nZU3ur/8S&RI<XMR3f:EQWOlinI75?,vB*3IX7,d^K}Cnb(n}1VfWBJX$_/ADg';

        $this->assertEquals($salt, $this->helper->getSalt());
    }

    /**
     * Should set the salt value.
     *
     * @test
     * @group unit
     * @return void
     */
    public function shouldSetSalt()
    {
        $newSalt = 'qwertyuiop';
        $this->helper->setSalt($newSalt);

        $this->assertEquals($newSalt, $this->helper->getSalt());
    }

    /**
     * Test the constructor.
     *
     * @group unit
     * @return void
     */
    public function testConstructor()
    {
        $newSalt = 'asdfghjkl';
        $helper = new EncryptHelper($newSalt);

        $this->assertEquals($newSalt, $helper->getSalt());
    }

    /**
     * Should encrypt a string.
     *
     * @group unit
     * @return void
     */
    public function shouldEncrypt()
    {
        $value = 'my favorite cookie';
        $encrypted = hash_hmac($this->getAlgo(), $value, $this->getSalt());

        $this->assertEquals($encrypted, $this->helper->encrypt($value));
    }

    /**
     * shouldMatch
     *
     * @group unit
     * @return void
     */
    public function shouldMatch()
    {
        $value = 'brownies are better';
        $encrypted = $this->helper->encrypt($value);

        $this->assertEquals($value, $encrypted);
    }
}


