<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Piwik_VisitTime
 */

class Piwik_VisitTime_Archiver extends Piwik_PluginsArchiver
{
    const SERVER_TIME_RECORD_NAME = 'VisitTime_serverTime';
    const LOCAL_TIME_RECORD_NAME = 'VisitTime_localTime';

    public function archiveDay()
    {
        $this->aggregateByLocalTime();
        $this->aggregateByServerTime();
    }

    protected function aggregateByServerTime()
    {
        $array = $this->getProcessor()->getMetricsForDimension( array("label" => "HOUR(log_visit.visit_last_action_time)" )) ;
        $query = $this->getLogAggregator()->queryConversionsByDimension( array("label" => "HOUR(log_conversion.server_time)") );
        if ($query === false) {
            return;
        }

        while ($row = $query->fetch()) {
            $array->sumMetricsGoals($row['label'], $row);
        }
        $array->enrichMetricsWithConversions();
        $array = $this->convertTimeToLocalTimezone($array);
        $this->ensureAllHoursAreSet($array);
        $this->getProcessor()->insertBlobRecord(self::SERVER_TIME_RECORD_NAME, $this->getProcessor()->getDataTableFromDataArray($array)->getSerialized());
    }

    protected function aggregateByLocalTime()
    {
        $array = $this->getProcessor()->getMetricsForDimension("HOUR(log_visit.visitor_localtime)");
        $this->ensureAllHoursAreSet($array);
        $this->getProcessor()->insertBlobRecord(self::LOCAL_TIME_RECORD_NAME, $this->getProcessor()->getDataTableFromDataArray($array)->getSerialized());
    }

    protected function convertTimeToLocalTimezone(Piwik_DataArray &$array)
    {
        $date = Piwik_Date::factory($this->getProcessor()->getDateStart()->getDateStartUTC())->toString();
        $timezone = $this->getProcessor()->getSite()->getTimezone();

        $converted = array();
        foreach ($array->getDataArray() as $hour => $stats) {
            $datetime = $date . ' ' . $hour . ':00:00';
            $hourInTz = (int)Piwik_Date::factory($datetime, $timezone)->toString('H');
            $converted[$hourInTz] = $stats;
        }
        return new Piwik_DataArray($converted);
    }


    private function ensureAllHoursAreSet( Piwik_DataArray &$array)
    {
        $data = $array->getDataArray();
        for ($i = 0; $i <= 23; $i++) {
            if (empty($data[$i])) {
                $array->sumMetricsVisits( $i, Piwik_DataArray::makeEmptyRow());
            }
        }
    }

    public function archivePeriod()
    {
        $dataTableToSum = array(
            self::LOCAL_TIME_RECORD_NAME,
            self::SERVER_TIME_RECORD_NAME,
        );
        $this->getProcessor()->aggregateDataTableReports($dataTableToSum);
    }
}