<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */
namespace Piwik\ArchiveProcessor;

use Piwik\ArchiveProcessor;
use Piwik\DataArray;
use Piwik\DataTable;
use Piwik\Metrics;
use Piwik\Piwik;

/**
 * This class
 * @package Piwik
 * @subpackage ArchiveProcessor
 *
 * @api
 */
class Day extends ArchiveProcessor
{
    /**
     * Converts the given array to a datatable
     * @param DataArray $array
     * @return \Piwik\DataTable
     */
    static public function getDataTableFromDataArray(DataArray $array)
    {
        $dataArray = $array->getDataArray();
        $dataArrayTwoLevels = $array->getDataArrayWithTwoLevels();

        $subtableByLabel = null;
        if (!empty($dataArrayTwoLevels)) {
            $subtableByLabel = array();
            foreach ($dataArrayTwoLevels as $label => $subTable) {
                $subtableByLabel[$label] = DataTable::makeFromIndexedArray($subTable);
            }
        }
        return DataTable::makeFromIndexedArray($dataArray, $subtableByLabel);
    }

    /**
     * Helper function that returns an array with common statistics for a given database field distinct values.
     *
     * The statistics returned are:
     *  - number of unique visitors
     *  - number of visits
     *  - number of actions
     *  - maximum number of action for a visit
     *  - sum of the visits' length in sec
     *  - count of bouncing visits (visits with one page view)
     *
     * For example if $dimension = 'config_os' it will return the statistics for every distinct Operating systems
     * The returned array will have a row per distinct operating systems,
     * and a column per stat (nb of visits, max  actions, etc)
     *
     * 'label'    Metrics::INDEX_NB_UNIQ_VISITORS    Metrics::INDEX_NB_VISITS    etc.
     * Linux    27    66    ...
     * Windows XP    12    ...
     * Mac OS    15    36    ...
     *
     * @param string $dimension Table log_visit field name to be use to compute common stats
     * @return DataArray
     */
    public function getMetricsForDimension($dimension)
    {
        if (!is_array($dimension)) {
            $dimension = array($dimension);
        }
        if (count($dimension) == 1) {
            $dimension = array("label" => reset($dimension));
        }
        $query = $this->getLogAggregator()->queryVisitsByDimension($dimension);
        $metrics = new DataArray();
        while ($row = $query->fetch()) {
            $metrics->sumMetricsVisits($row["label"], $row);
        }
        return $metrics;
    }

    protected function aggregateCoreVisitsMetrics()
    {
        $query = $this->getLogAggregator()->queryVisitsByDimension();
        $data = $query->fetch();

        $metrics = $this->convertMetricsIdToName($data);
        $this->insertNumericRecords($metrics);
        return $metrics;
    }

    protected function convertMetricsIdToName($data)
    {
        $metrics = array();
        foreach ($data as $metricId => $value) {
            $readableMetric = Metrics::$mappingFromIdToName[$metricId];
            $metrics[$readableMetric] = $value;
        }
        return $metrics;
    }

    protected function compute()
    {
        /**
         * This event is triggered when the archiver wants to compute a new archive. Use this event to archive your
         * custom report data if needed.
         *
         * Example:
         * ```
         * public function archiveDay(ArchiveProcessor\Day $archiveProcessor)
         * {
         *     $archiving = new Archiver($archiveProcessor);
         *     if ($archiving->shouldArchive()) {
         *         $archiving->archiveDay();
         *     }
         * }
         * ```
         */
        Piwik::postEvent('ArchiveProcessor.Day.compute', array(&$this));
    }
}
