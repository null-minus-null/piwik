<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
/**
 * Testing Period_Day
 */
class Period_DayTest extends PHPUnit_Framework_TestCase
{
    /**
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testInvalidDate()
    {
        try {
            $period = new Piwik_Period_Day('Invalid Date');
        } catch (Exception $e) {
            return;
        }
        $this->fail('Expected Exception not raised');
    }

    /**
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testToString()
    {
        $period = new Piwik_Period_Day(Piwik_Date::today());
        $this->assertEquals(date("Y-m-d"), $period->getPrettyString());
        $this->assertEquals(date("Y-m-d"), (string)$period);
        $this->assertEquals(date("Y-m-d"), $period->toString());
    }

    /**
     * today is NOT finished
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testDayIsFinishedToday()
    {
        $period = new Piwik_Period_Day(Piwik_Date::today());
        $this->assertEquals(date("Y-m-d"), $period->toString());
        $this->assertEquals(array(), $period->getSubperiods());
        $this->assertEquals(0, $period->getNumberOfSubperiods());
    }

    /**
     * yesterday 23:59:59 is finished
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testDayIsFinishedYesterday()
    {

        $period = new Piwik_Period_Day(Piwik_Date::yesterday());
        $this->assertEquals(date("Y-m-d", time() - 86400), $period->toString());
        $this->assertEquals(array(), $period->getSubperiods());
        $this->assertEquals(0, $period->getNumberOfSubperiods());
    }

    /**
     * tomorrow is not finished
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testDayIsFinishedTomorrow()
    {
        $period = new Piwik_Period_Day(Piwik_Date::factory(date("Y-m-d", time() + 86400)));
        $this->assertEquals(date("Y-m-d", time() + 86400), $period->toString());
        $this->assertEquals(array(), $period->getSubperiods());
        $this->assertEquals(0, $period->getNumberOfSubperiods());
    }

    /**
     * test day doesnt exist 31st feb
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testDayIsFinished31stfeb()
    {
        $period = new Piwik_Period_Day(Piwik_Date::factory("2007-02-31"));
        $this->assertEquals("2007-03-03", $period->toString());
        $this->assertEquals(array(), $period->getSubperiods());
        $this->assertEquals(0, $period->getNumberOfSubperiods());
    }

    /**
     * test date that doesn't exist, should return the corresponding correct date
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testDayGetDateStart1()
    {
        // create the period
        $period = new Piwik_Period_Day(Piwik_Date::factory("2007-02-31"));

        // start date
        $startDate = $period->getDateStart();

        // expected string
        $this->assertEquals("2007-03-03", $startDate->toString());

        // check that for a day, getDateStart = getStartEnd
        $this->assertEquals($startDate, $period->getDateEnd());
    }

    /**
     * test normal date
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testDayGetDateStart2()
    {
        // create the period
        $period = new Piwik_Period_Day(Piwik_Date::factory("2007-01-03"));

        // start date
        $startDate = $period->getDateStart();

        // expected string
        $this->assertEquals("2007-01-03", $startDate->toString());

        // check that for a day, getDateStart = getStartEnd
        $this->assertEquals($startDate, $period->getDateEnd());
    }

    /**
     * test last day of year
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testDayGetDateStart3()
    {
        // create the period
        $period = new Piwik_Period_Day(Piwik_Date::factory("2007-12-31"));

        // start date
        $startDate = $period->getDateStart();

        // expected string
        $this->assertEquals("2007-12-31", $startDate->toString());

        // check that for a day, getDateStart = getStartEnd
        $this->assertEquals($startDate, $period->getDateEnd());
    }

    /**
     * test date that doesn't exist, should return the corresponding correct date
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testDayGetDateEnd1()
    {
        // create the period
        $period = new Piwik_Period_Day(Piwik_Date::factory("2007-02-31"));

        // end date
        $endDate = $period->getDateEnd();

        // expected string
        $this->assertEquals("2007-03-03", $endDate->toString());
    }

    /**
     * test normal date
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testDayGetDateEnd2()
    {
        // create the period
        $period = new Piwik_Period_Day(Piwik_Date::factory("2007-04-15"));

        // end date
        $endDate = $period->getDateEnd();

        // expected string
        $this->assertEquals("2007-04-15", $endDate->toString());
    }

    /**
     * test last day of year
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testDayGetDateEnd3()
    {
        // create the period
        $period = new Piwik_Period_Day(Piwik_Date::factory("2007-12-31"));

        // end date
        $endDate = $period->getDateEnd();

        // expected string
        $this->assertEquals("2007-12-31", $endDate->toString());
    }

    /**
     * adding a subperiod should not be possible
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testAddSubperiodFails()
    {
        // create the period
        $period = new Piwik_Period_Day(Piwik_Date::factory("2007-12-31"));

        try {
            $period->addSubperiod('');
        } catch (Exception $e) {
            return;
        }
        // expected string
        $this->fail('Exception not raised');
    }

    /**
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testGetLocalizedShortString()
    {
        Piwik_Translate::getInstance()->loadEnglishTranslation();
        $month = new Piwik_Period_Day(Piwik_Date::factory('2024-10-09'));
        $shouldBe = 'Wed 9 Oct';
        $this->assertEquals($shouldBe, $month->getLocalizedShortString());
    }

    /**
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testGetLocalizedLongString()
    {
        Piwik_Translate::getInstance()->loadEnglishTranslation();
        $month = new Piwik_Period_Day(Piwik_Date::factory('2024-10-09'));
        $shouldBe = 'Wednesday 9 October 2024';
        $this->assertEquals($shouldBe, $month->getLocalizedLongString());
    }

    /**
     * @group Core
     * @group Period
     * @group Period_Day
     */
    public function testGetPrettyString()
    {
        Piwik_Translate::getInstance()->loadEnglishTranslation();
        $month = new Piwik_Period_Day(Piwik_Date::factory('2024-10-09'));
        $shouldBe = '2024-10-09';
        $this->assertEquals($shouldBe, $month->getPrettyString());
    }
}