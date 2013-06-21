<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
class DataTable_Filter_ExcludeLowPopulationTest extends PHPUnit_Framework_TestCase
{
    protected function getTestDataTable()
    {
        $table = new Piwik_DataTable;
        $table->addRowsFromArray(
            array(
                 array(Piwik_DataTable_Row::COLUMNS => array('label' => 'zero', 'count' => 0)),
                 array(Piwik_DataTable_Row::COLUMNS => array('label' => 'one', 'count' => 1)),
                 array(Piwik_DataTable_Row::COLUMNS => array('label' => 'onedotfive', 'count' => 1.5)),
                 array(Piwik_DataTable_Row::COLUMNS => array('label' => 'ten', 'count' => 10)),
                 array(Piwik_DataTable_Row::COLUMNS => array('label' => 'ninety', 'count' => 90)),
                 array(Piwik_DataTable_Row::COLUMNS => array('label' => 'hundred', 'count' => 100)),
            )
        );
        return $table;
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_ExcludeLowPopulation
     */
    public function testStandardTable()
    {
        $table = $this->getTestDataTable();
        $filter = new Piwik_DataTable_Filter_ExcludeLowPopulation($table, 'count', 1.1);
        $filter->filter($table);
        $this->assertEquals(4, $table->getRowsCount());
        $this->assertEquals(array(1.5, 10, 90, 100), $table->getColumn('count'));
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_ExcludeLowPopulation
     */
    public function testFilterEqualOneDoesFilter()
    {
        $table = $this->getTestDataTable();
        $filter = new Piwik_DataTable_Filter_ExcludeLowPopulation($table, 'count', 1);
        $filter->filter($table);
        $this->assertEquals(5, $table->getRowsCount());
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_ExcludeLowPopulation
     */
    public function testFilterEqualZeroDoesFilter()
    {
        $table = $this->getTestDataTable();
        $filter = new Piwik_DataTable_Filter_ExcludeLowPopulation($table, 'count', 0);
        $filter->filter($table);
        $this->assertEquals(3, $table->getRowsCount());
        $this->assertEquals(array(10, 90, 100), $table->getColumn('count'));
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_ExcludeLowPopulation
     */
    public function testFilterSpecifyExcludeLowPopulationThresholdDoesFilter()
    {
        $table = $this->getTestDataTable();
        $filter = new Piwik_DataTable_Filter_ExcludeLowPopulation($table, 'count', 0, 0.4); //40%
        $filter->filter($table);
        $this->assertEquals(2, $table->getRowsCount());
        $this->assertEquals(array(90, 100), $table->getColumn('count'));
    }


    /**
     * Test to exclude low population filter
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_ExcludeLowPopulation
     */
    public function testFilterLowpop1()
    {

        $idcol = Piwik_DataTable_Row::COLUMNS;

        $table = new Piwik_DataTable();
        $rows = array(
            array($idcol => array('label' => 'google', 'nb_visits' => 897)), //0
            array($idcol => array('label' => 'ask', 'nb_visits' => -152)), //1
            array($idcol => array('label' => 'piwik', 'nb_visits' => 1.5)), //2
            array($idcol => array('label' => 'piwik2', 'nb_visits' => 1.4)), //2
            array($idcol => array('label' => 'yahoo', 'nb_visits' => 154)), //3
            array($idcol => array('label' => 'amazon', 'nb_visits' => 30)), //4
            array($idcol => array('label' => '238949', 'nb_visits' => 0)), //5
            array($idcol => array('label' => 'Q*(%&*', 'nb_visits' => 1)), //6
            array($idcol => array('label' => 'Q*(%&*2', 'nb_visits' => -1.5)), //6
        );
        $table->addRowsFromArray($rows);

        $expectedtable = new Piwik_DataTable();
        $rows = array(
            array($idcol => array('label' => 'google', 'nb_visits' => 897)), //0
            array($idcol => array('label' => 'piwik', 'nb_visits' => 1.5)), //2
            array($idcol => array('label' => 'piwik2', 'nb_visits' => 1.4)), //2
            array($idcol => array('label' => 'yahoo', 'nb_visits' => 154)), //3
            array($idcol => array('label' => 'amazon', 'nb_visits' => 30)), //4
        );
        $expectedtable->addRowsFromArray($rows);

        $filter = new Piwik_DataTable_Filter_ExcludeLowPopulation($table, 'nb_visits', 1.4);
        $filter->filter($table);

        $this->assertTrue(Piwik_DataTable::isEqual($table, $expectedtable));
    }
}
