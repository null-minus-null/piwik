<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
use Piwik\Access;
use Piwik\AuthResult;

class AccessTest extends DatabaseTestCase
{
    public function setUp()
    {
        parent::setUp();
        Access::setSingletonInstance(null);
    }

    /**
     * @group Core
     * @group Access
     */
    public function testGetListAccess()
    {
        $accessList = Access::getListAccess();
        $shouldBe = array('noaccess', 'view', 'admin', 'superuser');
        $this->assertEquals($shouldBe, $accessList);
    }

    /**
     * @group Core
     * @group Access
     */
    public function testGetTokenAuthWithEmptyAccess()
    {
        $access = new Access();
        $this->assertNull($access->getTokenAuth());
    }

    /**
     * @group Core
     * @group Access
     */
    public function testGetLoginWithEmptyAccess()
    {
        $access = new Access();
        $this->assertNull($access->getLogin());
    }

    /**
     * @group Core
     * @group Access
     */
    public function testIsSuperUserWithEmptyAccess()
    {
        $access = new Access();
        $this->assertFalse($access->isSuperUser());
    }

    /**
     * @group Core
     * @group Access
     */
    public function testIsSuperUserWithSuperUserAccess()
    {
        $access = Access::getInstance();
        $access->setSuperUser(true);
        $this->assertTrue($access->isSuperUser());
    }

    /**
     * @group Core
     * @group Access
     */
    public function testIsSuperUserWithNoSuperUserAccess()
    {
        $access = Access::getInstance();
        $access->setSuperUser(false);
        $this->assertFalse($access->isSuperUser());
    }

    /**
     * @group Core
     * @group Access
     */
    public function testGetSitesIdWithAtLeastViewAccessWithEmptyAccess()
    {
        $access = new Access();
        $this->assertEmpty($access->getSitesIdWithAtLeastViewAccess());
    }

    /**
     * @group Core
     * @group Access
     */
    public function testGetSitesIdWithAdminAccessWithEmptyAccess()
    {
        $access = new Access();
        $this->assertEmpty($access->getSitesIdWithAdminAccess());
    }

    /**
     * @group Core
     * @group Access
     */
    public function testGetSitesIdWithViewAccessWithEmptyAccess()
    {
        $access = new Access();
        $this->assertEmpty($access->getSitesIdWithViewAccess());
    }

    /**
     * @group Core
     * @group Access
     * @expectedException Piwik\NoAccessException
     */
    public function testCheckUserIsSuperUserWithEmptyAccess()
    {
        $access = new Access();
        $access->checkUserIsSuperUser();
    }

    /**
     * @group Core
     * @group Access
     */
    public function testCheckUserIsSuperUserWithSuperUserAccess()
    {
        $access = Access::getInstance();
        $access->setSuperUser(true);
        $access->checkUserIsSuperUser();
    }

    /**
     * @group Core
     * @group Access
     * @expectedException Piwik\NoAccessException
     */
    public function testCheckUserHasSomeAdminAccessWithEmptyAccess()
    {
        $access = new Access();
        $access->checkUserHasSomeAdminAccess();
    }

    /**
     * @group Core
     * @group Access
     */
    public function testCheckUserHasSomeAdminAccessWithSuperUserAccess()
    {
        $access = Access::getInstance();
        $access->setSuperUser(true);
        $access->checkUserHasSomeAdminAccess();
    }

    /**
     * @group Core
     * @group Access
     */
    public function testCheckUserHasSomeAdminAccessWithSomeAccess()
    {
        $mock = $this->getMock(
            'Piwik\Access',
            array('getSitesIdWithAdminAccess')
        );

        $mock->expects($this->once())
             ->method('getSitesIdWithAdminAccess')
             ->will($this->returnValue(array(2, 9)));

        $mock->checkUserHasSomeAdminAccess();
    }

    /**
     * @group Core
     * @group Access
     * @expectedException Piwik\NoAccessException
     */
    public function testCheckUserHasSomeViewAccessWithEmptyAccess()
    {
        $access = new Access();
        $access->checkUserHasSomeViewAccess();
    }

    /**
     * @group Core
     * @group Access
     */
    public function testCheckUserHasSomeViewAccessWithSuperUserAccess()
    {
        $access = Access::getInstance();
        $access->setSuperUser(true);
        $access->checkUserHasSomeViewAccess();
    }

    /**
     * @group Core
     * @group Access
     */
    public function testCheckUserHasSomeViewAccessWithSomeAccess()
    {
        $mock = $this->getMock(
            'Piwik\Access',
            array('getSitesIdWithAtLeastViewAccess')
        );

        $mock->expects($this->once())
            ->method('getSitesIdWithAtLeastViewAccess')
            ->will($this->returnValue(array(1, 2, 3, 4)));

        $mock->checkUserHasSomeViewAccess();
    }

    /**
     * @group Core
     * @group Access
     * @expectedException Piwik\NoAccessException
     */
    public function testCheckUserHasViewAccessWithEmptyAccessNoSiteIdsGiven()
    {
        $access = new Access();
        $access->checkUserHasViewAccess(array());
    }

    /**
     * @group Core
     * @group Access
     */
    public function testCheckUserHasViewAccessWithSuperUserAccess()
    {
        $access = Access::getInstance();
        $access->setSuperUser(true);
        $access->checkUserHasViewAccess(array());
    }

    /**
     * @group Core
     * @group Access
     */
    public function testCheckUserHasViewAccessWithSomeAccessSuccessIdSitesAsString()
    {
        $mock = $this->getMock(
            'Piwik\Access',
            array('getSitesIdWithAtLeastViewAccess')
        );

        $mock->expects($this->once())
            ->method('getSitesIdWithAtLeastViewAccess')
            ->will($this->returnValue(array(1, 2, 3, 4)));

        $mock->checkUserHasViewAccess('1,3');
    }

    /**
     * @group Core
     * @group Access
     */
    public function testCheckUserHasViewAccessWithSomeAccessSuccessAllSites()
    {
        $mock = $this->getMock(
            'Piwik\Access',
            array('getSitesIdWithAtLeastViewAccess')
        );

        $mock->expects($this->any())
            ->method('getSitesIdWithAtLeastViewAccess')
            ->will($this->returnValue(array(1, 2, 3, 4)));

        $mock->checkUserHasViewAccess('all');
    }

    /**
     * @group Core
     * @group Access
     * @expectedException Piwik\NoAccessException
     */
    public function testCheckUserHasViewAccessWithSomeAccessFailure()
    {
        $mock = $this->getMock(
            'Piwik\Access',
            array('getSitesIdWithAtLeastViewAccess')
        );

        $mock->expects($this->once())
            ->method('getSitesIdWithAtLeastViewAccess')
            ->will($this->returnValue(array(1, 2, 3, 4)));

        $mock->checkUserHasViewAccess(array(1, 5));
    }

    /**
     * @group Core
     * @group Access
     */
    public function testCheckUserHasAdminAccessWithSuperUserAccess()
    {
        $access = Access::getInstance();
        $access->setSuperUser(true);
        $access->checkUserHasAdminAccess(array());
    }

    /**
     * @group Core
     * @group Access
     * @expectedException Piwik\NoAccessException
     */
    public function testCheckUserHasAdminAccessWithEmptyAccessNoSiteIdsGiven()
    {
        $access = new Access();
        $access->checkUserHasViewAccess(array());
    }

    /**
     * @group Core
     * @group Access
     */
    public function testCheckUserHasAdminAccessWithSomeAccessSuccessIdSitesAsString()
    {
        $mock = $this->getMock(
            'Piwik\Access',
            array('getSitesIdWithAdminAccess')
        );

        $mock->expects($this->once())
            ->method('getSitesIdWithAdminAccess')
            ->will($this->returnValue(array(1, 2, 3, 4)));

        $mock->checkUserHasAdminAccess('1,3');
    }

    /**
     * @group Core
     * @group Access
     */
    public function testCheckUserHasAdminAccessWithSomeAccessSuccessAllSites()
    {
        $mock = $this->getMock(
            'Piwik\Access',
            array('getSitesIdWithAdminAccess', 'getSitesIdWithAtLeastViewAccess')
        );

        $mock->expects($this->any())
            ->method('getSitesIdWithAdminAccess')
            ->will($this->returnValue(array(1, 2, 3, 4)));

        $mock->expects($this->any())
            ->method('getSitesIdWithAtLeastViewAccess')
            ->will($this->returnValue(array(1, 2, 3, 4)));

        $mock->checkUserHasAdminAccess('all');
    }

    /**
     * @group Core
     * @group Access
     * @expectedException Piwik\NoAccessException
     */
    public function testCheckUserHasAdminAccessWithSomeAccessFailure()
    {
        $mock = $this->getMock(
            'Piwik\Access',
            array('getSitesIdWithAdminAccess')
        );

        $mock->expects($this->once())
            ->method('getSitesIdWithAdminAccess')
            ->will($this->returnValue(array(1, 2, 3, 4)));

        $mock->checkUserHasAdminAccess(array(1, 5));
    }

    /**
     * @group Core
     * @group Access
     */
    public function testReloadAccessWithEmptyAuth()
    {
        $access = new Access();
        $this->assertFalse($access->reloadAccess(null));
    }

    /**
     * @group Core
     * @group Access
     */
    public function testReloadAccessWithEmptyAuthSuperUser()
    {
        $access = Access::getInstance();
        $access->setSuperUser(true);
        $this->assertTrue($access->reloadAccess(null));
    }

    /**
     * @group Core
     * @group Access
     */
    public function testReloadAccessWithMockedAuthValid()
    {
        $mock = $this->getMock('\\Piwik\\Auth', array('authenticate', 'getName'));
        $mock->expects($this->once())
            ->method('authenticate')
            ->will($this->returnValue(new AuthResult(AuthResult::SUCCESS, 'login', 'token')));

        $mock->expects($this->any())->method('getName')->will($this->returnValue("test name"));

        $access = Access::getInstance();
        $this->assertTrue($access->reloadAccess($mock));
        $this->assertFalse($access->isSuperUser());
    }
}