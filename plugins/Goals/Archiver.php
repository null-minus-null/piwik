<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Piwik_Goals
 */

class Piwik_Goals_Archiver extends Piwik_PluginsArchiver
{
    const VISITS_UNTIL_RECORD_NAME = 'visits_until_conv';
    const DAYS_UNTIL_CONV_RECORD_NAME = 'days_until_conv';
    const ITEMS_SKU_RECORD_NAME = 'Goals_ItemsSku';
    const ITEMS_NAME_RECORD_NAME = 'Goals_ItemsName';
    const ITEMS_CATEGORY_RECORD_NAME = 'Goals_ItemsCategory';
    const SKU_FIELD = 'idaction_sku';
    const NAME_FIELD = 'idaction_name';
    const CATEGORY_FIELD = 'idaction_category';
    const CATEGORY2_FIELD = 'idaction_category2';
    const CATEGORY3_FIELD = 'idaction_category3';
    const CATEGORY4_FIELD = 'idaction_category4';
    const CATEGORY5_FIELD = 'idaction_category5';
    const NO_LABEL = ':';
    const LOG_CONVERSION_TABLE = 'log_conversion';
    const VISITS_COUNT_FIELD = 'visitor_count_visits';
    const DAYS_SINCE_FIRST_VISIT_FIELD = 'visitor_days_since_first';
    /**
     * This array stores the ranges to use when displaying the 'visits to conversion' report
     */
    public static $visitCountRanges = array(
        array(1, 1),
        array(2, 2),
        array(3, 3),
        array(4, 4),
        array(5, 5),
        array(6, 6),
        array(7, 7),
        array(8, 8),
        array(9, 14),
        array(15, 25),
        array(26, 50),
        array(51, 100),
        array(100)
    );
    /**
     * This array stores the ranges to use when displaying the 'days to conversion' report
     */
    public static $daysToConvRanges = array(
        array(0, 0),
        array(1, 1),
        array(2, 2),
        array(3, 3),
        array(4, 4),
        array(5, 5),
        array(6, 6),
        array(7, 7),
        array(8, 14),
        array(15, 30),
        array(31, 60),
        array(61, 120),
        array(121, 364),
        array(364)
    );
    protected $dimensionRecord = array(
        self::SKU_FIELD      => self::ITEMS_SKU_RECORD_NAME,
        self::NAME_FIELD     => self::ITEMS_NAME_RECORD_NAME,
        self::CATEGORY_FIELD => self::ITEMS_CATEGORY_RECORD_NAME
    );

    /**
     * Array containing one DataArray for each Ecommerce items dimension (name/sku/category abandoned carts and orders)
     * @var array
     */
    protected $itemReports = array();

    public function archiveDay()
    {
        $this->archiveGeneralGoalMetrics();
        $this->archiveEcommerceItems();
    }

    protected function archiveGeneralGoalMetrics()
    {
        $prefixes = array(
            self::VISITS_UNTIL_RECORD_NAME    => 'vcv',
            self::DAYS_UNTIL_CONV_RECORD_NAME => 'vdsf',
        );
        $aggregatesMetadata = array(
            array(self::VISITS_COUNT_FIELD, self::$visitCountRanges, self::LOG_CONVERSION_TABLE, $prefixes[self::VISITS_UNTIL_RECORD_NAME]),
            array(self::DAYS_SINCE_FIRST_VISIT_FIELD, self::$daysToConvRanges, self::LOG_CONVERSION_TABLE, $prefixes[self::DAYS_UNTIL_CONV_RECORD_NAME]),
        );
        $selects = array();
        foreach ($aggregatesMetadata as $aggregateMetadata) {
            $selects = array_merge($selects, Piwik_DataAccess_LogAggregator::getSelectsFromRangedColumn($aggregateMetadata));
        }

        $query = $this->getLogAggregator()->queryConversionsByDimension(array(), false, $selects);
        if ($query === false) {
            return;
        }

        $totalConversions = $totalRevenue = 0;
        $goals = new Piwik_DataArray();
        $visitsToConversions = $daysToConversions = array();

        $conversionMetrics = $this->getLogAggregator()->getConversionsMetricFields();
        while ($row = $query->fetch()) {
            $idGoal = $row['idgoal'];
            unset($row['idgoal']);
            unset($row['label']);

            $values = array();
            foreach($conversionMetrics as $field => $statement) {
                $values[$field] = $row[$field];
            }
            $goals->sumMetrics($idGoal, $values);

            if (empty($visitsToConversions[$idGoal])) {
                $visitsToConversions[$idGoal] = new Piwik_DataTable();
            }
            $array = Piwik_DataAccess_LogAggregator::makeArrayOneColumn($row, Piwik_Metrics::INDEX_NB_CONVERSIONS, $prefixes[self::VISITS_UNTIL_RECORD_NAME]);
            $visitsToConversions[$idGoal]->addDataTable(Piwik_DataTable::makeFromIndexedArray($array));

            if (empty($daysToConversions[$idGoal])) {
                $daysToConversions[$idGoal] = new Piwik_DataTable();
            }
            $array = Piwik_DataAccess_LogAggregator::makeArrayOneColumn($row, Piwik_Metrics::INDEX_NB_CONVERSIONS, $prefixes[self::DAYS_UNTIL_CONV_RECORD_NAME]);
            $daysToConversions[$idGoal]->addDataTable(Piwik_DataTable::makeFromIndexedArray($array));

            // We don't want to sum Abandoned cart metrics in the overall revenue/conversions/converted visits
            // since it is a "negative conversion"
            if ($idGoal != Piwik_Tracker_GoalManager::IDGOAL_CART) {
                $totalConversions += $row[Piwik_Metrics::INDEX_GOAL_NB_CONVERSIONS];
                $totalRevenue += $row[Piwik_Metrics::INDEX_GOAL_REVENUE];
            }
        }

        // Stats by goal, for all visitors
        $numericRecords = $this->getConversionsNumericMetrics($goals);
        $this->getProcessor()->insertNumericRecords($numericRecords);

        $this->insertReports(self::VISITS_UNTIL_RECORD_NAME, $visitsToConversions);
        $this->insertReports(self::DAYS_UNTIL_CONV_RECORD_NAME, $daysToConversions);

        // Stats for all goals
        $nbConvertedVisits = $this->getProcessor()->getNumberOfVisitsConverted();
        $metrics = array(
            self::getRecordName('conversion_rate')     => $this->getConversionRate($nbConvertedVisits),
            self::getRecordName('nb_conversions')      => $totalConversions,
            self::getRecordName('nb_visits_converted') => $nbConvertedVisits,
            self::getRecordName('revenue')             => $totalRevenue,
        );
        $this->getProcessor()->insertNumericRecords($metrics);
    }

    protected function getConversionsNumericMetrics(Piwik_DataArray $goals)
    {
        $numericRecords = array();
        $goals = $goals->getDataArray();
        foreach ($goals as $idGoal => $array) {
            foreach ($array as $metricId => $value) {
                $metricName = Piwik_Metrics::$mappingFromIdToNameGoal[$metricId];
                $recordName = self::getRecordName($metricName, $idGoal);
                $numericRecords[$recordName] = $value;
            }
            if(!empty($array[Piwik_Metrics::INDEX_GOAL_NB_VISITS_CONVERTED])) {
                $conversion_rate = $this->getConversionRate($array[Piwik_Metrics::INDEX_GOAL_NB_VISITS_CONVERTED]);
                $recordName = self::getRecordName('conversion_rate', $idGoal);
                $numericRecords[$recordName] = $conversion_rate;
            }
        }
        return $numericRecords;
    }

    /**
     * @param string $recordName 'nb_conversions'
     * @param int|bool $idGoal idGoal to return the metrics for, or false to return overall
     * @return string Archive record name
     */
    static public function getRecordName($recordName, $idGoal = false)
    {
        $idGoalStr = '';
        if ($idGoal !== false) {
            $idGoalStr = $idGoal . "_";
        }
        return 'Goal_' . $idGoalStr . $recordName;
    }

    protected function getConversionRate($count)
    {
        $visits = $this->getProcessor()->getNumberOfVisits();
        return round(100 * $count / $visits, Piwik_Tracker_GoalManager::REVENUE_PRECISION);
    }

    protected function insertReports($recordName, $visitsToConversions)
    {
        foreach ($visitsToConversions as $idGoal => $table) {
            $record = self::getRecordName($recordName, $idGoal);
            $this->getProcessor()->insertBlobRecord($record, $table->getSerialized());
        }
        $overviewTable = $this->getOverviewFromGoalTables($visitsToConversions);
        $this->getProcessor()->insertBlobRecord(self::getRecordName($recordName), $overviewTable->getSerialized());
    }

    protected function getOverviewFromGoalTables($tableByGoal)
    {
        $overview = new Piwik_DataTable();
        foreach ($tableByGoal as $idGoal => $table) {
            if ($this->isStandardGoal($idGoal)) {
                $overview->addDataTable($table);
            }
        }
        return $overview;
    }

    protected function isStandardGoal($idGoal)
    {
        return !in_array($idGoal, $this->getEcommerceIdGoals());
    }

    protected function archiveEcommerceItems()
    {
        if (!$this->shouldArchiveEcommerceItems()) {
            return false;
        }
        $this->initItemReports();
        foreach ($this->getItemsDimensions() as $dimension) {
            $query = $this->getLogAggregator()->queryEcommerceItems($dimension);
            if ($query == false) {
                continue;
            }
            $this->aggregateFromEcommerceItems($query, $dimension);
        }
        $this->recordItemReports();
    }

    protected function initItemReports()
    {
        foreach ($this->getEcommerceIdGoals() as $ecommerceType) {
            foreach ($this->dimensionRecord as $dimension => $record) {
                $this->itemReports[$dimension][$ecommerceType] = new Piwik_DataArray();
            }
        }
    }

    protected function recordItemReports()
    {
        /** @var Piwik_DataArray $array */
        foreach ($this->itemReports as $dimension => $itemAggregatesByType) {
            foreach ($itemAggregatesByType as $ecommerceType => $itemAggregate) {
                $recordName = $this->dimensionRecord[$dimension];
                if ($ecommerceType == Piwik_Tracker_GoalManager::IDGOAL_CART) {
                    $recordName = self::getItemRecordNameAbandonedCart($recordName);
                }
                $table = $this->getProcessor()->getDataTableFromDataArray($itemAggregate);
                $this->getProcessor()->insertBlobRecord($recordName, $table->getSerialized());
            }
        }
    }

    protected function shouldArchiveEcommerceItems()
    {
        // Per item doesn't support segment
        // Also, when querying Goal metrics for visitorType==returning, we wouldnt want to trigger an extra request
        // event if it did support segment
        if (!$this->getProcessor()->getSegment()->isEmpty()) {
            return false;
        }
        return true;
    }

    protected function getItemsDimensions()
    {
        $dimensions = array_keys($this->dimensionRecord);
        foreach ($this->getItemExtraCategories() as $category) {
            $dimensions[] = $category;
        }
        return $dimensions;
    }

    protected function getItemExtraCategories()
    {
        return array(self::CATEGORY2_FIELD, self::CATEGORY3_FIELD, self::CATEGORY4_FIELD, self::CATEGORY5_FIELD);
    }

    protected function isItemExtraCategory($field)
    {
        return in_array($field, $this->getItemExtraCategories());
    }

    protected function aggregateFromEcommerceItems($query, $dimension)
    {
        while ($row = $query->fetch()) {
            $ecommerceType = $row['ecommerceType'];

            $label = $this->cleanupRowGetLabel($row, $dimension);
            if ($label === false) {
                continue;
            }

            // Aggregate extra categories in the Item categories array
            if ($this->isItemExtraCategory($dimension)) {
                $array = $this->itemReports[self::CATEGORY_FIELD][$ecommerceType];
            } else {
                $array = $this->itemReports[$dimension][$ecommerceType];
            }

            $this->roundColumnValues($row);
            $array->sumMetrics($label, $row);
        }
    }

    protected function cleanupRowGetLabel(&$row, $currentField)
    {
        $label = $row['label'];
        if (empty($label)) {
            // An empty additional category -> skip this iteration
            if ($this->isItemExtraCategory($currentField)) {
                return false;
            }
            $label = "Value not defined";
            // Product Name/Category not defined"
            if (class_exists('Piwik_CustomVariables')) {
                $label = Piwik_CustomVariables_Archiver::LABEL_CUSTOM_VALUE_NOT_DEFINED;
            }
        }

        if ($row['ecommerceType'] == Piwik_Tracker_GoalManager::IDGOAL_CART) {
            // abandoned carts are the numner of visits with an abandoned cart
            $row[Piwik_Metrics::INDEX_ECOMMERCE_ORDERS] = $row[Piwik_Metrics::INDEX_NB_VISITS];
        }

        unset($row[Piwik_Metrics::INDEX_NB_VISITS]);
        unset($row['label']);
        unset($row['ecommerceType']);

        return $label;
    }

    protected function roundColumnValues(&$row)
    {
        $columnsToRound = array(
            Piwik_Metrics::INDEX_ECOMMERCE_ITEM_REVENUE,
            Piwik_Metrics::INDEX_ECOMMERCE_ITEM_QUANTITY,
            Piwik_Metrics::INDEX_ECOMMERCE_ITEM_PRICE,
            Piwik_Metrics::INDEX_ECOMMERCE_ITEM_PRICE_VIEWED,
        );
        foreach ($columnsToRound as $column) {
            if (isset($row[$column])
                && $row[$column] == round($row[$column])
            ) {
                $row[$column] = round($row[$column]);
            }
        }
    }

    protected function getEcommerceIdGoals()
    {
        return array(Piwik_Tracker_GoalManager::IDGOAL_CART, Piwik_Tracker_GoalManager::IDGOAL_ORDER);
    }

    static public function getItemRecordNameAbandonedCart($recordName)
    {
        return $recordName . '_Cart';
    }

    /**
     * @param $this->getProcessor()
     */
    public function archivePeriod()
    {
        /*
         * Archive Ecommerce Items
         */
        if ($this->shouldArchiveEcommerceItems()) {
            $dataTableToSum = $this->dimensionRecord;
            foreach ($this->dimensionRecord as $recordName) {
                $dataTableToSum[] = self::getItemRecordNameAbandonedCart($recordName);
            }
            $this->getProcessor()->aggregateDataTableReports($dataTableToSum);
        }

        /*
         *  Archive General Goal metrics
         */
        $goalIdsToSum = Piwik_Tracker_GoalManager::getGoalIds($this->getProcessor()->getSite()->getId());

        //Ecommerce
        $goalIdsToSum[] = Piwik_Tracker_GoalManager::IDGOAL_ORDER;
        $goalIdsToSum[] = Piwik_Tracker_GoalManager::IDGOAL_CART; //bug here if idgoal=1
        // Overall goal metrics
        $goalIdsToSum[] = false;

        $fieldsToSum = array();
        foreach ($goalIdsToSum as $goalId) {
            $metricsToSum = Piwik_Goals::getGoalColumns($goalId);
            unset($metricsToSum[array_search('conversion_rate', $metricsToSum)]);
            foreach ($metricsToSum as $metricName) {
                $fieldsToSum[] = self::getRecordName($metricName, $goalId);
            }
        }
        $records = $this->getProcessor()->aggregateNumericMetrics($fieldsToSum);

        // also recording conversion_rate for each goal
        foreach ($goalIdsToSum as $goalId) {
            $nb_conversions = $records[self::getRecordName('nb_visits_converted', $goalId)];
            $conversion_rate = $this->getConversionRate($nb_conversions);
            $this->getProcessor()->insertNumericRecord(self::getRecordName('conversion_rate', $goalId), $conversion_rate);

            // sum up the visits to conversion data table & the days to conversion data table
            $this->getProcessor()->aggregateDataTableReports(array(
                                                         self::getRecordName(self::VISITS_UNTIL_RECORD_NAME, $goalId),
                                                         self::getRecordName(self::DAYS_UNTIL_CONV_RECORD_NAME, $goalId)));
        }

        // sum up goal overview reports
        $this->getProcessor()->aggregateDataTableReports(array(
                                                     self::getRecordName(self::VISITS_UNTIL_RECORD_NAME),
                                                     self::getRecordName(self::DAYS_UNTIL_CONV_RECORD_NAME)));
    }
}