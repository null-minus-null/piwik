<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * This tests the output of the API plugin API
 * It will return metadata about all API reports from all plugins
 * as well as the data itself, pre-processed and ready to be displayed
 */
class Test_Piwik_Integration_ApiGetReportMetadata extends IntegrationTestCase
{
    public static $fixture = null; // initialized below class definition

    public function setUp()
    {
        parent::setUp();

        // From Piwik 1.5, we hide Goals.getConversions and other get* methods via @ignore, but we
        // ensure that they still work. This hack allows the API proxy to let us generate example
        // URLs for the ignored functions
        Piwik_API_Proxy::getInstance()->setHideIgnoredFunctions(false);
    }

    public function tearDown()
    {
        parent::tearDown();

        // reset that value after the test
        Piwik_API_Proxy::getInstance()->setHideIgnoredFunctions(true);
    }

    public function getOutputPrefix()
    {
        return 'apiGetReportMetadata';
    }

    public function getApiForTesting()
    {
        $idSite = self::$fixture->idSite;
        $dateTime = self::$fixture->dateTime;

        return array(
            array('API', array('idSite' => $idSite, 'date' => $dateTime)),

            // test w/ hideMetricsDocs=true
            array('API.getMetadata', array('idSite'                 => $idSite, 'date' => $dateTime,
                                           'apiModule'              => 'Actions', 'apiAction' => 'get',
                                           'testSuffix'             => '_hideMetricsDoc',
                                           'otherRequestParameters' => array('hideMetricsDoc' => 1))),
            array('API.getProcessedReport', array('idSite'                 => $idSite, 'date' => $dateTime,
                                                  'apiModule'              => 'Actions', 'apiAction' => 'get',
                                                  'testSuffix'             => '_hideMetricsDoc',
                                                  'otherRequestParameters' => array('hideMetricsDoc' => 1))),

            // Test w/ showRawMetrics=true
            array('API.getProcessedReport', array('idSite'                 => $idSite, 'date' => $dateTime,
                                                  'apiModule'              => 'UserCountry', 'apiAction' => 'getCountry',
                                                  'testSuffix'             => '_showRawMetrics',
                                                  'otherRequestParameters' => array('showRawMetrics' => 1))),

            // Test w/ showRawMetrics=true
            array('Actions.getPageTitles', array('idSite'     => $idSite, 'date' => $dateTime,
                                                 'testSuffix' => '_pageTitleZeroString')),

            // test php renderer w/ array data
            array('API.getDefaultMetricTranslations', array('idSite' => $idSite, 'date' => $dateTime,
                                                            'format' => 'php', 'testSuffix' => '_phpRenderer')),
        );
    }

    /**
     * @dataProvider getApiForTesting
     * @group        Integration
     * @group        ApiGetReportMetadata
     */
    public function testApi($api, $params)
    {
        $this->runApiTests($api, $params);
    }
}

Test_Piwik_Integration_ApiGetReportMetadata::$fixture = new Test_Piwik_Fixture_ThreeGoalsOnePageview();

