<?php
/**
 * Piwik - Open source web analytics
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * This use case covers many simple tracking features.
 * - Tracking Goal by manual trigger, and URL matching, with custom revenue
 * - Tracking the same Goal twice only records it once
 * - Tracks 4 page views: 3 clicks and a file download
 * - URLs parameters exclude is tested
 * - In a returning visit, tracks a Goal conversion
 *   URL matching, with custom referer and keyword
 *   NO cookie support
 */
class Test_Piwik_Integration_OneVisitorTwoVisits extends IntegrationTestCase
{
    public static $fixture = null; // initialized below class

    public function setUp()
    {
        Piwik_API_Proxy::getInstance()->setHideIgnoredFunctions(false);
    }

    public function tearDown()
    {
        Piwik_API_Proxy::getInstance()->setHideIgnoredFunctions(true);
    }

    /**
     * @dataProvider getApiForTesting
     * @group        Integration
     * @group        OneVisitorTwoVisits
     */
    public function testApi($api, $params)
    {
        $this->runApiTests($api, $params);
    }

    public function getApiForTesting()
    {
        $idSite = self::$fixture->idSite;
        $dateTime = self::$fixture->dateTime;

        $enExtraParam = array('expanded' => 1, 'flat' => 1, 'include_aggregate_rows' => 0, 'translateColumnNames' => 1);
        $bulkUrls = array(
            "idSite=" . $idSite . "&date=2010-03-06&expanded=1&period=day&method=VisitsSummary.get",
            "idSite=" . $idSite . "&date=2010-03-06&expanded=1&period=day&method=VisitorInterest.getNumberOfVisitsPerVisitDuration"
        );
        foreach ($bulkUrls as &$url) {
            $url = urlencode($url);
        }
        return array(
            array('all', array('idSite' => $idSite, 'date' => $dateTime)),

            // test API.get (for bug that incorrectly reorders columns of CSV output)
            //   note: bug only affects rows after first
            array('API.get', array('idSite'                 => $idSite,
                                   'date'                   => '2009-10-01',
                                   'format'                 => 'csv',
                                   'periods'                => array('month'),
                                   'setDateLastN'           => true,
                                   'otherRequestParameters' => $enExtraParam,
                                   'language'               => 'en',
                                   'testSuffix'             => '_csv')),

            array('API.getBulkRequest', array('format' => 'xml',
                                               'testSuffix' => '_bulk_xml',
                                               'otherRequestParameters' => array('urls' => $bulkUrls))),

            array('API.getBulkRequest', array('format' => 'json',
                                              'testSuffix' => '_bulk_json',
                                              'otherRequestParameters' => array('urls' => $bulkUrls))),

            // test API.getProcessedReport w/ report that is its own 'actionToLoadSubTables'
            array('API.getProcessedReport', array('idSite'        => $idSite,
                                                  'date'          => $dateTime,
                                                  'periods'       => array('week'),
                                                  'apiModule'     => 'Actions',
                                                  'apiAction'     => 'getPageUrls',
                                                  'supertableApi' => 'Actions.getPageUrls',
                                                  'testSuffix'    => '__subtable')),

            // test hideColumns && showColumns parameters
            array('VisitsSummary.get', array('idSite'                 => $idSite, 'date' => $dateTime, 'periods' => 'day',
                                             'testSuffix'             => '_hideColumns_',
                                             'otherRequestParameters' => array(
                                                 'hideColumns' => 'nb_visits_converted,max_actions,bounce_count,nb_hits,'
                                                     . 'nb_visits,nb_actions,sum_visit_length,avg_time_on_site'
                                             ))),
            array('VisitsSummary.get', array('idSite'                 => $idSite, 'date' => $dateTime, 'periods' => 'day',
                                             'testSuffix'             => '_showColumns_',
                                             'otherRequestParameters' => array(
                                                 'showColumns' => 'nb_visits,nb_actions,nb_hits'
                                             ))),
            array('VisitsSummary.get', array('idSite'                 => $idSite, 'date' => $dateTime, 'periods' => 'day',
                                             'testSuffix'             => '_hideAllColumns_',
                                             'otherRequestParameters' => array(
                                                 'hideColumns' => 'nb_visits_converted,max_actions,bounce_count,nb_hits,'
                                                     . 'nb_visits,nb_actions,sum_visit_length,avg_time_on_site,'
                                                     . 'bounce_rate,nb_uniq_visitors,nb_actions_per_visit,'
                                             ))),

            // test hideColumns w/ API.getProcessedReport
            array('API.getProcessedReport', array('idSite'                 => $idSite, 'date' => $dateTime,
                                                  'periods'                => 'day', 'apiModule' => 'Actions',
                                                  'apiAction'              => 'getPageTitles', 'testSuffix' => '_hideColumns_',
                                                  'otherRequestParameters' => array(
                                                      'hideColumns' => 'nb_visits_converted,xyzaug,entry_nb_visits,' .
                                                          'bounce_rate,nb_hits,nb_visits,avg_time_on_page,' .
														  'avg_time_generation,nb_hits_with_time_generation'
                                                  ))),

            array('API.getProcessedReport', array('idSite'                 => $idSite, 'date' => $dateTime,
                                                  'periods'                => 'day', 'apiModule' => 'Actions',
                                                  'apiAction'              => 'getPageTitles', 'testSuffix' => '_showColumns_',
                                                  'otherRequestParameters' => array(
                                                      'showColumns' => 'nb_visits_converted,xuena,entry_nb_visits,' .
                                                          'bounce_rate,nb_hits'
                                                  ))),
            array('API.getProcessedReport', array('idSite'                 => $idSite, 'date' => $dateTime,
                                                  'periods'                => 'day', 'apiModule' => 'VisitTime',
                                                  'apiAction'              => 'getVisitInformationPerServerTime',
                                                  'testSuffix'             => '_showColumnsWithProcessedMetrics_',
                                                  'otherRequestParameters' => array(
                                                      'showColumns' => 'nb_visits,revenue'
                                                  ))),

            // test hideColumns w/ expanded=1
            array('Actions.getPageTitles', array('idSite'                 => $idSite, 'date' => $dateTime,
                                                 'periods'                => 'day', 'testSuffix' => '_hideColumns_',
                                                 'otherRequestParameters' => array(
                                                     'hideColumns' => 'nb_visits_converted,entry_nb_visits,' .
                                                         'bounce_rate,nb_hits,nb_visits,sum_time_spent,' .
                                                         'entry_sum_visit_length,entry_bounce_count,exit_nb_visits,' .
                                                         'entry_nb_uniq_visitors,exit_nb_uniq_visitors,entry_nb_actions,' .
                                                         'avg_time_generation,nb_hits_with_time_generation',
                                                     'expanded'    => '1'
                                                 ))),

            // test showColumns on API.get
            array('API.get', array(
                'idSite'                 => $idSite,
                'date'                   => $dateTime,
                'periods'                => 'day',
                'testSuffix'             => '_showColumns', 
                'otherRequestParameters' => array(
                    'showColumns'        => 'nb_uniq_visitors,nb_pageviews,bounce_rate'
                )
            )),
        );
    }

    /**
     * Test that Archive_Single::preFetchBlob won't fetch extra unnecessary blobs.
     *
     * @group        Integration
     * @group        OneVisitorTwoVisits
     */
    public function testArchiveSinglePreFetchBlob()
    {
        $archive = Piwik_Archive::build(self::$fixture->idSite, 'day', self::$fixture->dateTime);
        $cache = $archive->getBlob('Actions_actions', 'all');

        $foundSubtable = false;

        $this->assertTrue(count($cache) > 0, "empty blob cache");
        foreach ($cache as $name => $value) {
            $this->assertTrue(strpos($name, "Actions_actions_url") === false, "found blob w/ name '$name'");

            if (strpos($name, "Actions_actions_") !== false) {
                $foundSubtable = true;
            }
        }

        $this->assertTrue($foundSubtable, "Actions_actions subtable was not loaded");
    }
    
    /**
     * Test that restricting the number of sites to those viewable to another login
     * works when building an archive query object.
     * 
     * @group        Integration
     * @group        OneVisitorTwoVisits
     */
    public function testArchiveSitesWhenRestrictingToLogin()
    {
        try
        {
            Piwik_Archive::build(
                'all', 'day', self::$fixture->dateTime, $segment = false, $_restrictToLogin = 'anotherLogin');
            $this->fail("Restricting sites to invalid login did not return 0 sites.");
        }
        catch (Exception $ex)
        {
            // pass
        }
    }
}

Test_Piwik_Integration_OneVisitorTwoVisits::$fixture = new Test_Piwik_Fixture_OneVisitorTwoVisits();
Test_Piwik_Integration_OneVisitorTwoVisits::$fixture->excludeMozilla = true;

