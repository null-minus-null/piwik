<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
class DataTable_Filter_LimitTest extends PHPUnit_Framework_TestCase
{
    /**
     * Returns table used for the tests
     *
     * @return Piwik_DataTable
     */
    protected function getDataTableCount10()
    {
        $table = new Piwik_DataTable;
        $idcol = Piwik_DataTable_Row::COLUMNS;
        $rows = array(
            array($idcol => array('label' => 'google', 'idRow' => 0)),
            array($idcol => array('label' => 'ask', 'idRow' => 1)),
            array($idcol => array('label' => 'piwik', 'idRow' => 2)),
            array($idcol => array('label' => 'yahoo', 'idRow' => 3)),
            array($idcol => array('label' => 'amazon', 'idRow' => 4)),
            array($idcol => array('label' => '238949', 'idRow' => 5)),
            array($idcol => array('label' => 'test', 'idRow' => 6)),
            array($idcol => array('label' => 'amazing', 'idRow' => 7)),
            array($idcol => array('label' => 'great', 'idRow' => 8)),
            Piwik_DataTable::ID_SUMMARY_ROW => array($idcol => array('label' => 'summary row', 'idRow' => 9)),
        );
        $table->addRowsFromArray($rows);
        return $table;
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testNormal()
    {
        $offset = 2;
        $limit = 3;
        $table = $this->getDataTableCount10();
        $filter = new Piwik_DataTable_Filter_Limit($table, $offset, $limit);
        $filter->filter($table);
        $this->assertEquals(3, $table->getRowsCount());
        $this->assertEquals(2, $table->getFirstRow()->getColumn('idRow'));
        $this->assertEquals(4, $table->getLastRow()->getColumn('idRow'));
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testLimitLessThanCountShouldReturnCountLimit()
    {
        $offset = 2;
        $limit = 7;
        $table = $this->getDataTableCount10();
        $filter = new Piwik_DataTable_Filter_Limit($table, $offset, $limit);
        $filter->filter($table);
        $this->assertEquals(7, $table->getRowsCount());
        $this->assertEquals(2, $table->getFirstRow()->getColumn('idRow'));
        $this->assertEquals(8, $table->getLastRow()->getColumn('idRow'));
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testLimitIsCountShouldNotDeleteAnything()
    {
        $offset = 0;
        $limit = 10;
        $table = $this->getDataTableCount10();
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
        $filter = new Piwik_DataTable_Filter_Limit($table, $offset, $limit);
        $filter->filter($table);
        $this->assertEquals(10, $table->getRowsCount());
        $this->assertEquals(0, $table->getFirstRow()->getColumn('idRow'));
        $this->assertEquals(9, $table->getLastRow()->getColumn('idRow'));
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testLimitGreaterThanCountShouldReturnCountUntilCount()
    {
        $offset = 5;
        $limit = 20;
        $table = $this->getDataTableCount10();
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
        $filter = new Piwik_DataTable_Filter_Limit($table, $offset, $limit);
        $filter->filter($table);
        $this->assertEquals(5, $table->getRowsCount());
        $this->assertEquals(5, $table->getFirstRow()->getColumn('idRow'));
        $this->assertEquals(9, $table->getLastRow()->getColumn('idRow'));
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testLimitIsNullShouldReturnCountIsOffset()
    {
        $offset = 1;
        $table = $this->getDataTableCount10();
        $filter = new Piwik_DataTable_Filter_Limit($table, $offset);
        $filter->filter($table);
        $this->assertEquals(9, $table->getRowsCount());
        $this->assertEquals(1, $table->getFirstRow()->getColumn('idRow'));
        $this->assertEquals(9, $table->getLastRow()->getColumn('idRow'));
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testOffsetJustBeforeSummaryRowShouldJustReturnSummaryRow()
    {
        $offset = 9;
        $limit = 1;
        $table = $this->getDataTableCount10();
        $filter = new Piwik_DataTable_Filter_Limit($table, $offset, $limit);
        $filter->filter($table);
        $this->assertEquals(1, $table->getRowsCount());
        $this->assertEquals(9, $table->getFirstRow()->getColumn('idRow'));
        $this->assertEquals(9, $table->getLastRow()->getColumn('idRow'));
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testOffsetJustBeforeSummaryRowWithBigLimitShouldJustReturnSummaryRow()
    {
        $offset = 9;
        $limit = 100;
        $table = $this->getDataTableCount10();
        $filter = new Piwik_DataTable_Filter_Limit($table, $offset, $limit);
        $filter->filter($table);
        $this->assertEquals(1, $table->getRowsCount());
        $this->assertEquals(9, $table->getFirstRow()->getColumn('idRow'));
        $this->assertEquals(9, $table->getLastRow()->getColumn('idRow'));
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testOffsetBeforeSummaryRowShouldJustReturnRowAndSummaryRow()
    {
        $offset = 8;
        $limit = 3;
        $table = $this->getDataTableCount10();
        $filter = new Piwik_DataTable_Filter_Limit($table, $offset, $limit);
        $filter->filter($table);
        $this->assertEquals(2, $table->getRowsCount());
        $this->assertEquals(8, $table->getFirstRow()->getColumn('idRow'));
        $this->assertEquals(9, $table->getLastRow()->getColumn('idRow'));
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testOffsetGreaterThanCountShouldReturnEmptyTable()
    {
        $offset = 10;
        $limit = 10;
        $table = $this->getDataTableCount10();
        $filter = new Piwik_DataTable_Filter_Limit($table, $offset, $limit);
        $filter->filter($table);
        $this->assertEquals(0, $table->getRowsCount());
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
    }

    /**
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testLimitIsZeroShouldReturnEmptyTable()
    {
        $offset = 0;
        $limit = 0;
        $table = $this->getDataTableCount10();
        $filter = new Piwik_DataTable_Filter_Limit($table, $offset, $limit);
        $filter->filter($table);
        $this->assertEquals(0, $table->getRowsCount());
        $this->assertEquals(10, $table->getRowsCountBeforeLimitFilter());
    }

    /**
     * Test to filter a table with a offset, limit
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testFilterOffsetLimit()
    {
        $table = new Piwik_DataTable;

        $idcol = Piwik_DataTable_Row::COLUMNS;

        $rows = array(
            array($idcol => array('label' => 'google')), //0
            array($idcol => array('label' => 'ask')), //1
            array($idcol => array('label' => 'piwik')), //2
            array($idcol => array('label' => 'yahoo')), //3
            array($idcol => array('label' => 'amazon')), //4
            array($idcol => array('label' => '238975247578949')), //5
            array($idcol => array('label' => 'Q*(%&*("$&%*(&"$*")"))')) //6
        );

        $table->addRowsFromArray($rows);

        $expectedtable = clone $table;
        $expectedtable->deleteRows(array(0, 1, 6));

        $filter = new Piwik_DataTable_Filter_Limit($table, 2, 4);
        $filter->filter($table);

        $colAfter = $colExpected = array();
        foreach ($table->getRows() as $row) $colAfter[] = $row->getColumn('label');
        foreach ($expectedtable->getRows() as $row) $colExpected[] = $row->getColumn('label');

        $this->assertEquals(array_values($expectedtable->getRows()), array_values($table->getRows()));
    }

    /**
     * Test to filter a column with a offset, limit off bound
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testFilterOffsetLimitOffbound()
    {
        $table = new Piwik_DataTable;

        $idcol = Piwik_DataTable_Row::COLUMNS;

        $rows = array(
            array($idcol => array('label' => 'google')), //0
            array($idcol => array('label' => 'ask')), //1
            array($idcol => array('label' => 'piwik')), //2
            array($idcol => array('label' => 'yahoo')), //3
            array($idcol => array('label' => 'amazon')), //4
            array($idcol => array('label' => '238975247578949')), //5
            array($idcol => array('label' => 'Q*(%&*("$&%*(&"$*")"))')) //6
        );

        $table->addRowsFromArray($rows);

        $expectedtable = clone $table;
        $expectedtable->deleteRows(array(0, 1, 3, 4, 5, 6));

        $filter = new Piwik_DataTable_Filter_Limit($table, 2, 1);
        $filter->filter($table);

        $colAfter = $colExpected = array();
        foreach ($table->getRows() as $row) $colAfter[] = $row->getColumn('label');
        foreach ($expectedtable->getRows() as $row) $colExpected[] = $row->getColumn('label');

        $this->assertEquals(array_values($expectedtable->getRows()), array_values($table->getRows()));
    }

    /**
     * Test to filter a column with a offset, limit 2
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testFilterOffsetLimit2()
    {
        $table = new Piwik_DataTable;

        $idcol = Piwik_DataTable_Row::COLUMNS;

        $rows = array(
            array($idcol => array('label' => 'google')), //0
            array($idcol => array('label' => 'ask')), //1
            array($idcol => array('label' => 'piwik')), //2
            array($idcol => array('label' => 'yahoo')), //3
            array($idcol => array('label' => 'amazon')), //4
            array($idcol => array('label' => '238975247578949')), //5
            array($idcol => array('label' => 'Q*(%&*("$&%*(&"$*")"))')) //6
        );

        $table->addRowsFromArray($rows);

        $expectedtable = clone $table;

        $filter = new Piwik_DataTable_Filter_Limit($table, 0, 15);
        $filter->filter($table);

        $colAfter = $colExpected = array();
        foreach ($table->getRows() as $row) $colAfter[] = $row->getColumn('label');
        foreach ($expectedtable->getRows() as $row) $colExpected[] = $row->getColumn('label');

        $this->assertEquals(array_values($expectedtable->getRows()), array_values($table->getRows()));
    }

    /**
     * Test to filter a column with a offset, limit 3
     *
     * @group Core
     * @group DataTable
     * @group DataTable_Filter
     * @group DataTable_Filter_Limit
     */
    public function testFilterOffsetLimit3()
    {
        $table = new Piwik_DataTable;

        $idcol = Piwik_DataTable_Row::COLUMNS;

        $rows = array(
            array($idcol => array('label' => 'google')), //0
            array($idcol => array('label' => 'ask')), //1
            array($idcol => array('label' => 'piwik')), //2
            array($idcol => array('label' => 'yahoo')), //3
            array($idcol => array('label' => 'amazon')), //4
            array($idcol => array('label' => '238975247578949')), //5
            array($idcol => array('label' => 'Q*(%&*("$&%*(&"$*")"))')) //6
        );

        $table->addRowsFromArray($rows);

        $expectedtable = new Piwik_DataTable;

        $filter = new Piwik_DataTable_Filter_Limit($table, 8, 15);
        $filter->filter($table);

        $colAfter = $colExpected = array();
        foreach ($table->getRows() as $row) $colAfter[] = $row->getColumn('label');
        foreach ($expectedtable->getRows() as $row) $colExpected[] = $row->getColumn('label');

        $this->assertEquals(array_values($expectedtable->getRows()), array_values($table->getRows()));
    }

}
