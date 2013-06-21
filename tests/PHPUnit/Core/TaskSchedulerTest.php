<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
class TaskSchedulerTest extends PHPUnit_Framework_TestCase
{
    private static function getTestTimetable()
    {
        return array(
            'Piwik_CoreAdminHome.purgeOutdatedArchives' => 1355529607,
            'Piwik_PrivacyManager.deleteReportData_1'   => 1322229607,
        );
    }

    /**
     * Dataprovider for testGetTimetableFromOptionValue
     */
    public function getTimetableFromOptionValueTestCases()
    {
        return array(

            // invalid option values should return a fresh array
            array(array(), false),
            array(array(), null),
            array(array(), 1),
            array(array(), ''),
            array(array(), 'test'),

            // valid serialized array
            array(
                array(
                    'Piwik_CoreAdminHome.purgeOutdatedArchives' => 1355529607,
                    'Piwik_PrivacyManager.deleteReportData'     => 1355529607,
                ),
                'a:2:{s:41:"Piwik_CoreAdminHome.purgeOutdatedArchives";i:1355529607;s:37:"Piwik_PrivacyManager.deleteReportData";i:1355529607;}'
            ),
        );
    }

    /**
     * @group Core
     * @group TaskScheduler
     * @dataProvider getTimetableFromOptionValueTestCases
     */
    public function testGetTimetableFromOptionValue($expectedTimetable, $option)
    {
        $getTimetableFromOptionValue = new ReflectionMethod(
            'Piwik_TaskScheduler', 'getTimetableFromOptionValue'
        );
        $getTimetableFromOptionValue->setAccessible(TRUE);

        $this->assertEquals($expectedTimetable, $getTimetableFromOptionValue->invoke(new Piwik_TaskScheduler(), $option));
    }

    /**
     * Dataprovider for testTaskHasBeenScheduledOnce
     */
    public function taskHasBeenScheduledOnceTestCases()
    {
        $timetable = self::getTestTimetable();

        return array(
            array(true, 'Piwik_CoreAdminHome.purgeOutdatedArchives', $timetable),
            array(true, 'Piwik_PrivacyManager.deleteReportData_1', $timetable),
            array(false, 'Piwik_PDFReports.weeklySchedule"', $timetable)
        );
    }

    /**
     * @group Core
     * @group TaskScheduler
     * @dataProvider taskHasBeenScheduledOnceTestCases
     */
    public function testTaskHasBeenScheduledOnce($expectedDecision, $taskName, $timetable)
    {
        $taskHasBeenScheduledOnce = new ReflectionMethod(
            'Piwik_TaskScheduler', 'taskHasBeenScheduledOnce'
        );
        $taskHasBeenScheduledOnce->setAccessible(TRUE);

        $this->assertEquals($expectedDecision, $taskHasBeenScheduledOnce->invoke(new Piwik_TaskScheduler(), $taskName, $timetable));
    }

    /**
     * Dataprovider for testGetScheduledTimeForMethod
     */
    public function getScheduledTimeForMethodTestCases()
    {
        $timetable = serialize(self::getTestTimetable());

        return array(
            array(1355529607, 'Piwik_CoreAdminHome', 'purgeOutdatedArchives', null, $timetable),
            array(1322229607, 'Piwik_PrivacyManager', 'deleteReportData', 1, $timetable),
            array(false, 'Piwik_PDFReports', 'weeklySchedule', null, $timetable)
        );
    }

    /**
     * @group Core
     * @group TaskScheduler
     * @dataProvider getScheduledTimeForMethodTestCases
     */
    public function testGetScheduledTimeForMethod($expectedTime, $className, $methodName, $methodParameter, $timetable)
    {
        self::stubPiwikOption($timetable);

        $this->assertEquals($expectedTime, Piwik_TaskScheduler::getScheduledTimeForMethod($className, $methodName, $methodParameter));

        self::resetPiwikOption();
    }

    /**
     * Dataprovider for testTaskShouldBeExecuted
     */
    public function taskShouldBeExecutedTestCases()
    {
        $timetable = self::getTestTimetable();

        // set a date in the future (should not run)
        $timetable['Piwik_CoreAdminHome.purgeOutdatedArchives'] = time() + 60000;

        // set now (should run)
        $timetable['Piwik_PrivacyManager.deleteReportData_1'] = time();

        return array(
            array(false, 'Piwik_CoreAdminHome.purgeOutdatedArchives', $timetable),
            array(true, 'Piwik_PrivacyManager.deleteReportData_1', $timetable),
            array(false, 'Piwik_PDFReports.weeklySchedule"', $timetable)
        );
    }

    /**
     * @group Core
     * @group TaskScheduler
     * @dataProvider taskShouldBeExecutedTestCases
     */
    public function testTaskShouldBeExecuted($expectedDecision, $taskName, $timetable)
    {
        $taskShouldBeExecuted = new ReflectionMethod(
            'Piwik_TaskScheduler', 'taskShouldBeExecuted'
        );
        $taskShouldBeExecuted->setAccessible(TRUE);

        $this->assertEquals($expectedDecision, $taskShouldBeExecuted->invoke(new Piwik_TaskScheduler(), $taskName, $timetable));
    }

    /**
     * Dataprovider for testExecuteTask
     */
    public function executeTaskTestCases()
    {
        return array(
            array('scheduledTaskOne', null),
            array('scheduledTaskTwo', 'parameterValue'),
            array('scheduledTaskTwo', 1),
        );
    }

    /**
     * @group Core
     * @group TaskScheduler
     * @dataProvider executeTaskTestCases
     */
    public function testExecuteTask($methodName, $parameterValue)
    {
        // assert the scheduled method is executed once with the correct parameter
        $mock = $this->getMock('TaskSchedulerTest', array($methodName));
        $mock->expects($this->once())->method($methodName)->with($this->equalTo($parameterValue));

        $executeTask = new ReflectionMethod('Piwik_TaskScheduler', 'executeTask');
        $executeTask->setAccessible(TRUE);

        $this->assertNotEmpty($executeTask->invoke(
            new Piwik_TaskScheduler(),
            new Piwik_ScheduledTask ($mock, $methodName, $parameterValue, new Piwik_ScheduledTime_Daily())
        ));
    }

    /**
     * Dataprovider for testRunTasks
     */
    public function testRunTasksTestCases()
    {
        $systemTime = time();

        $dailySchedule = $this->getMock('Piwik_ScheduledTime_Daily', array('getTime'));
        $dailySchedule->expects($this->any())
            ->method('getTime')
            ->will($this->returnValue($systemTime));

        $scheduledTaskOne = new Piwik_ScheduledTask ($this, 'scheduledTaskOne', null, $dailySchedule);
        $scheduledTaskTwo = new Piwik_ScheduledTask ($this, 'scheduledTaskTwo', 1, $dailySchedule);
        $scheduledTaskThree = new Piwik_ScheduledTask ($this, 'scheduledTaskThree', null, $dailySchedule);

        $caseOneExpectedTable = array(
            'TaskSchedulerTest.scheduledTaskOne'   => $scheduledTaskOne->getRescheduledTime(),
            'TaskSchedulerTest.scheduledTaskTwo_1' => $systemTime + 60000,
            'TaskSchedulerTest.scheduledTaskThree' => $scheduledTaskThree->getRescheduledTime(),
        );

        $caseTwoTimetableBeforeExecution = $caseOneExpectedTable;
        $caseTwoTimetableBeforeExecution['TaskSchedulerTest.scheduledTaskThree'] = $systemTime; // simulate elapsed time between case 1 and 2

        return array(

            // case 1) contains :
            // - scheduledTaskOne: already scheduled before, should be executed and rescheduled
            // - scheduledTaskTwo: already scheduled before, should not be executed and therefore not rescheduled
            // - scheduledTaskThree: not already scheduled before, should be scheduled but not executed
            array(
                $caseOneExpectedTable,

                // methods that should be executed
                array(
                    'TaskSchedulerTest.scheduledTaskOne'
                ),

                // timetable before task execution
                array(
                    'TaskSchedulerTest.scheduledTaskOne'   => $systemTime,
                    'TaskSchedulerTest.scheduledTaskTwo_1' => $systemTime + 60000,
                ),
                // configured tasks
                array(
                    $scheduledTaskOne,
                    $scheduledTaskTwo,
                    $scheduledTaskThree,
                )
            ),

            // case 2) follows case 1) with :
            // - scheduledTaskOne: already scheduled before, should not be executed and therefore not rescheduled
            // - scheduledTaskTwo: not configured for execution anymore, should be removed from the timetable
            // - scheduledTaskThree: already scheduled before, should be executed and rescheduled
            array(
                // expected timetable
                array(
                    'TaskSchedulerTest.scheduledTaskOne'   => $scheduledTaskOne->getRescheduledTime(),
                    'TaskSchedulerTest.scheduledTaskThree' => $scheduledTaskThree->getRescheduledTime()
                ),

                // methods that should be executed
                array(
                    'TaskSchedulerTest.scheduledTaskThree'
                ),

                // timetable before task execution
                $caseTwoTimetableBeforeExecution,

                // configured tasks
                array(
                    $scheduledTaskOne,
//					$scheduledTaskTwo, Not configured anymore (ie. not returned after Piwik_TaskScheduler::GET_TASKS_EVENT is issued)
                    $scheduledTaskThree,
                )
            ),
        );
    }

    public function scheduledTaskOne()
    {
    } // nothing to do
    public function scheduledTaskTwo($param)
    {
    } // nothing to do
    public function scheduledTaskThree()
    {
    } // nothing to do

    /**
     * @group Core
     * @group TaskScheduler
     * @dataProvider testRunTasksTestCases
     */
    public function testRunTasks($expectedTimetable, $expectedExecutedTasks, $timetableBeforeTaskExecution, $configuredTasks)
    {
        // stub the event dispatcher so we can control the returned event notification
        Piwik_PluginsManager::getInstance()->dispatcher = new MockEventDispatcher($configuredTasks);

        // stub the piwik option object to control the returned option value
        self::stubPiwikOption(serialize($timetableBeforeTaskExecution));

        // execute tasks
        $executionResults = Piwik_TaskScheduler::runTasks();

        // assert methods are executed
        $executedTasks = array();
        foreach ($executionResults as $executionResult) {
            $executedTasks[] = $executionResult['task'];
            $this->assertNotEmpty($executionResult['output']);
        }
        $this->assertEquals($expectedExecutedTasks, $executedTasks);

        // assert the timetable is correctly updated
        $getTimetableFromOptionTable = new ReflectionMethod('Piwik_TaskScheduler', 'getTimetableFromOptionTable');
        $getTimetableFromOptionTable->setAccessible(TRUE);
        $this->assertEquals($expectedTimetable, $getTimetableFromOptionTable->invoke(new Piwik_TaskScheduler()));

        // restore event dispatcher & piwik options
        Piwik_PluginsManager::getInstance()->dispatcher = Event_Dispatcher::getInstance();
        self::resetPiwikOption();
    }

    private static function stubPiwikOption($timetable)
    {
        self::getReflectedPiwikOptionInstance()->setValue(new MockPiwikOption($timetable));
    }

    private static function resetPiwikOption()
    {
        self::getReflectedPiwikOptionInstance()->setValue(null);
    }

    private static function getReflectedPiwikOptionInstance()
    {
        $piwikOptionInstance = new ReflectionProperty('Piwik_Option', 'instance');
        $piwikOptionInstance->setAccessible(true);
        return $piwikOptionInstance;
    }
}
