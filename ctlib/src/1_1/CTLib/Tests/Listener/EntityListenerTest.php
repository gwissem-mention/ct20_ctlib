<?php

namespace CTLib\Tests\Listener;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * EntityListener tests.
 *
 * @author K. Gustavson <kgustavson@celltrak.com>
 */
class EntityListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Should be true test.
     *
     * @test
     * @group unit
     */
    public function shouldBeTrue()
    {
        $this->assertTrue(true);
    }
    /**
     * Should be false test.
     *
     * @test
     * @group unit
     */
    public function shouldBeFalse()
    {
        $this->assertFalse(false);
    }

    /**
     * Should insert the correct housekeeping data.
     *
     * @todo this should be a unit test.
     * @t est
     */
    public function shouldInsertCorrectHousekeeping()
    {
        // Create a new Member and do NOT explicitly set housekeeping
        $member = new \AppBundle\Entity\Member();
        $member->setClientKey('TEST');
        $member->setName1('TEST');
        $member->setName2('TEST');
        $member->setCurrentStatus('TEST');
        $member->setAddressCurrentIsValidated(0);
        $member->setMemberTypeId('STAFF');
        $member->setCurrentDeviceStatus('');

        $this->em()->persist($member);
        $this->em()->flush();

        // Re-read entity
        // Added-by and Modified-by should match my MemberId
    }

    /**
     * Should only modify the update housekeeping data.
     *
     * @todo this should be a unit test.
     * @t est
     */
    public function shouldUpdateCorrectHousekeeping()
    {
        // Get an existing Member and update currentStatus
        $member = $this->repo('Member')->mustFind(30152);
        $member->setCurrentStatus('HOLD');

        $this->em()->persist($member);
        $this->em()->flush();

        // Re-read entity
        // Modified-by should match my MemberId
    }

    /**
     * Should insert the correct housekeeping data for effective entities.
     *
     * @todo this should be a unit test.
     * @t est
     */
    public function shouldInsertCorrectEffectiveHousekeeping()
    {
        // TO DO this needs to be finished
        $member = new \AppBundle\Entity\Member;

        // Create a new MemberStatus and do NOT explicitly set housekeeping
        $status = new \AppBundle\Entity\MemberStatus;
        $status->setMember($member);
        $status->setStatus('NEW');

        $this->em()->persist($status);
        $this->em()->flush();

        // Re-read entity
        // Added-by and Modified-by should match my MemberId
    }

    /**
     * Updates should create a new effective entity instance.
     *
     * @todo this should be a unit test.
     * @t est
     */
    public function shouldUpdateCorrectEffectiveHousekeeping()
    {
        // Get an existing MemberStatus and update status
        $member = $this->repo('Member')->mustFind(30153);

        $status = $this->repo('MemberStatus')->findEffective($member);
        $status->setStatus('UPDAT');

        $this->em()->persist($status);
        $this->em()->flush();


        // Re-read entity
        // Modified-by should match my MemberId
    }
}
