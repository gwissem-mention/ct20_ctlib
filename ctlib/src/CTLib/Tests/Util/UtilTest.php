<?php
namespace CTLib\Tests\Util;

use CTLib\Util\Util;

/**
 * Unit tests for Util class.
 */
class UtilTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests the append method.
     *
     * @group unit
     * @return void
     */
    public function testAppend()
    {
        $value = Util::append('something', '.ext');
        $this->assertEquals($value, 'something.ext');

        $value = Util::append('something.ext', '.ext');
        $this->assertEquals($value, 'something.ext');

        $value = Util::append('something.ext.else', '.ext');
        $this->assertEquals($value, 'something.ext.else.ext');
    }

    /**
     * Tests the prepend method.
     *
     * @group unit
     * @return void
     */
    public function testPrepend()
    {
        $value = Util::prepend('something', 'pre_');
        $this->assertEquals($value, 'pre_something');

        $value = Util::prepend('pre_something', 'pre_');
        $this->assertEquals($value, 'pre_something');

        $value = Util::prepend('somethingpre_.else', 'pre_');
        $this->assertEquals($value, 'pre_somethingpre_.else');
    }
}
