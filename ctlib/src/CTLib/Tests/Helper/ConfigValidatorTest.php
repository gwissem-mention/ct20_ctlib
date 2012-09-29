<?php
namespace CTLib\Tests\Helper;

use CTLib\Helper\ConfigValidator;

/**
 * Class ConfigValidatorTest
 *
 * @author Kevin Gustavson <kgustavson@celltrak.com>
 */
class ConfigValidatorTest extends \PHPUnit_Framework_Testcase
{
    /**
     * Run before each test.
     *
     * @return void
     */
    public function setUp()
    {
        $this->config = array(
            's1' => 'string',
            's2' => 'str',
            'i1' => 'integer',
            'i2' => 'int',
            'i3' => '+integer',
            'i4' => '+int',
            'i5' => '-integer',
            'i6' => '-int',
            'f1' => 'float',
            'f2' => '+float',
            'f3' => '-float',
            'b'  => 'boolean',
            'a'  => 'array',
            'm'  => array(true, 'false'),
            'r'  => '/^member/',
        );
        $this->helper = new ConfigValidator($this->config);
    }

    /**
     * Test isValid will return false if key isn't in validationRules.
     *
     * @group unit
     * @return void
     */
    public function testIsValidKeyNotFound()
    {
        $this->assertFalse($this->helper->isValid('blabla', 12.93));
    }

    /**
     * Test isValid when conditions pass.
     *
     * @group unit
     * @return void
     */
    public function testIsValidPassing()
    {
        $this->assertTrue($this->helper->isValid('s1', 'wala wala bing bang'));
        $this->assertTrue($this->helper->isValid('s2', 'wala wala bing bang'));
        $this->assertTrue($this->helper->isValid('i1', 12));
        $this->assertTrue($this->helper->isValid('i2', -11));
        $this->assertTrue($this->helper->isValid('i3', 116));
        $this->assertTrue($this->helper->isValid('i4', 916));
        $this->assertTrue($this->helper->isValid('i5', -83));
        $this->assertTrue($this->helper->isValid('i6', -99));
        $this->assertTrue($this->helper->isValid('f1', 12.53));
        $this->assertTrue($this->helper->isValid('f2', 1.5e5));
        $this->assertTrue($this->helper->isValid('f3', -22.79));
        $this->assertTrue($this->helper->isValid('b', true));
        $this->assertTrue($this->helper->isValid('b', false));
        $this->assertTrue($this->helper->isValid('a', [1, 2, 3]));
        $this->assertTrue($this->helper->isValid('m', true));
        $this->assertTrue($this->helper->isValid('m', 'false'));
        $this->assertTrue($this->helper->isValid('r', 'memberType'));
    }

    /**
     * Test isValid when conditions fail.
     *
     * @group unit
     * @return void
     */
    public function testIsValidFailing()
    {
        $this->assertFalse($this->helper->isValid('s1', 15));
        $this->assertFalse($this->helper->isValid('s2', true));
        $this->assertFalse($this->helper->isValid('i1', 12.1));
        $this->assertFalse($this->helper->isValid('i2', 'eleven'));
        $this->assertFalse($this->helper->isValid('i3', 12.6));
        $this->assertFalse($this->helper->isValid('i4', -2));
        $this->assertFalse($this->helper->isValid('i5', 83));
        $this->assertFalse($this->helper->isValid('i6', false));
        $this->assertFalse($this->helper->isValid('f1', 12));
        $this->assertFalse($this->helper->isValid('f2', [12.67]));
        $this->assertFalse($this->helper->isValid('f3', 13.0));
        $this->assertFalse($this->helper->isValid('b', [true]));
        $this->assertFalse($this->helper->isValid('b', 0));
        $this->assertFalse($this->helper->isValid('a', 'blue'));
        $this->assertFalse($this->helper->isValid('m', 'true'));
        $this->assertFalse($this->helper->isValid('m', false));
        $this->assertFalse($this->helper->isValid('r', 'memb3rType'));
    }

    /**
     * Test getConfigKeys.
     *
     * @group unit
     * @return void
     */
    public function testGetConfigKeys()
    {
        $configKeys = array_keys($this->config);
        $this->assertSame($configKeys, $this->helper->getConfigKeys());
    }

    /**
     * Test configuration can be loaded from a file.
     *
     * @group unit
     * @return void
     */
    public function testLoadConfigurationFromFile()
    {
        // TODO can I mock the Yaml classes too?
        //$this->markTestSkipped('The namespace hack is actually working. Now I am missing Symfony\'s Yaml classes. :(');
        //$this->assertEquals(expected, actual);

        $newHelper = $this->helper->createFromFile('/some/path');
        $this->assertInstanceOf('\CTLib\Helper\ConfigValidator', $newHelper);
        $this->assertEquals(
            [
                'activity.exec_sources',
                'activity.filter_groups',
                'activity.travel_distance_sources',
                'activity.travel_time_sources',
                'activity.export_when_alerts_cleared'
            ],
            $newHelper->getConfigKeys()
        );
    }
}

/* ********************************** */
/* MOCK existing or missing functions */
/* ********************************** */
namespace CTLib\Helper;
/**
 * Mock file_get_contents function.
 *
 * @param string $path
 *
 * @return string
 */
function file_get_contents($path)
{
    return <<< EOT
# Activities.
activity.exec_sources: array
activity.filter_groups: array
activity.travel_distance_sources: array
activity.travel_time_sources: array
activity.export_when_alerts_cleared: bool
EOT;

}

namespace Symfony\Component\Yaml;
/**
 * Mock Yaml class.
 */
class Yaml
{
    /**
     * Mock parse function.
     *
     * @param mixed $content
     *
     * @return array
     */
    public static function parse($content)
    {
        return [
            'activity.exec_sources'               => 'array',
            'activity.filter_groups'              => 'array',
            'activity.travel_distance_sources'    => 'array',
            'activity.travel_time_sources'        => 'array',
            'activity.export_when_alerts_cleared' => 'bool'
        ];
    }
}

