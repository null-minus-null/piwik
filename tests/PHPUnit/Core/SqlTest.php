<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
class SqlTest extends DatabaseTestCase
{
    public function setUp()
    {
        parent::setUp();

        // create two myisam tables
        Piwik_Exec("CREATE TABLE table1 (a INT) ENGINE=MYISAM");
        Piwik_Exec("CREATE TABLE table2 (b INT) ENGINE=MYISAM");

        // create two innodb tables
        Piwik_Exec("CREATE TABLE table3 (c INT) ENGINE=InnoDB");
        Piwik_Exec("CREATE TABLE table4 (d INT) ENGINE=InnoDB");
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @group Core
     * @group Unzip
     */
    public function testOptimize()
    {
        // make sure optimizing myisam tables works
        $this->assertTrue(Piwik_OptimizeTables(array('table1', 'table2')) !== false);

        // make sure optimizing both myisam & innodb results in optimizations
        $this->assertTrue(Piwik_OptimizeTables(array('table1', 'table2', 'table3', 'table4')) !== false);

        // make sure innodb tables are skipped
        $this->assertTrue(Piwik_OptimizeTables(array('table3', 'table4')) === false);
    }
}
