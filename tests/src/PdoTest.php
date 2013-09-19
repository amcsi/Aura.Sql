<?php
namespace Aura\Sql;

class PdoTest extends \PHPUnit_Framework_TestCase
{
    protected $pdo;
    
    protected $expect_quote_scalar = "'\"foo\" bar ''baz'''";
    
    protected $expect_quote_array = "'\"foo\"', 'bar', '''baz'''";
    
    protected $expect_quote_into = "foo = '''bar'''";
    
    protected $expect_quote_values_in = "foo = '''bar''' AND zim = '''baz'''";
    
    protected $expect_quote_multi = "id = 1 AND foo = 'bar' AND zim IN('dib', 'gir', 'baz')";
    
    protected $expect_quote_name_table_as_alias = '"table" AS "alias"';
    
    protected $expect_quote_name_table_col_as_alias = '"table"."col" AS "alias"';
    
    protected $expect_quote_name_table_alias = '"table" "alias"';
    
    protected $expect_quote_name_table_col_alias = '"table"."col" "alias"';
    
    protected $expect_quote_name_plain = '"table"';
    
    protected $expect_quote_names_in = "*, *.*, \"foo\".\"bar\", CONCAT('foo.bar', \"baz.dib\") AS \"zim\"";
    
    public function setUp()
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped("Need 'pdo_sqlite' to test in memory.");
        }
        $this->pdo = new Pdo('sqlite::memory:');

        $dsn = 'sqlite::memory:';
        $username = null;
        $password = null;
        $driver_options = null;
        
        // do this to test constructor array loop
        $attributes = array(Pdo::ATTR_ERRMODE => Pdo::ERRMODE_EXCEPTION);
        
        $this->pdo = new Pdo(
            $dsn,
            $username,
            $password,
            $driver_options,
            $attributes
        );

        $this->createTable();
        $this->fillTable();
    }
    
    protected function createTable()
    {
        $stm = "CREATE TABLE pdotest (
            id   INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(10) NOT NULL
        )";
        
        $this->pdo->exec($stm);
    }
    
    // only fills in schema 1
    protected function fillTable()
    {
        $names = array(
            'Anna', 'Betty', 'Clara', 'Donna', 'Fiona',
            'Gertrude', 'Hanna', 'Ione', 'Julia', 'Kara',
        );
        
        foreach ($names as $name) {
            $this->insert(array('name' => $name));
        }
    }
    
    protected function insert(array $data)
    {
        $cols = array_keys($data);
        $vals = array();
        foreach ($cols as $col) {
            $vals[] = ":$col";
        }
        $cols = implode(', ', $cols);
        $vals = implode(', ', $vals);
        $stm = "INSERT INTO pdotest ({$cols}) VALUES ({$vals})";
        $this->pdo->bindValues($data);
        $this->pdo->exec($stm);
    }
    
    public function testErrorCodeAndInfo()
    {
        $actual = $this->pdo->errorCode();
        $expect = '00000';
        $this->assertSame($expect, $actual);
        
        $actual = $this->pdo->errorInfo();
        $expect = array('00000', null, null);
        $this->assertSame($expect, $actual);
    }
    
    public function testSetAndGetAttribute()
    {
        $pdo = new Pdo('sqlite::memory:');
        $this->assertFalse($pdo->isConnected());
        
        $pdo->setAttribute(Pdo::ATTR_ERRMODE, Pdo::ERRMODE_WARNING);
        $this->assertFalse($pdo->isConnected());
        
        $actual = $pdo->getAttribute(Pdo::ATTR_ERRMODE);
        $this->assertSame(Pdo::ERRMODE_WARNING, $actual);
        $this->assertTrue($pdo->isConnected());
        
        // set again now that we're connected
        $pdo->setAttribute(Pdo::ATTR_ERRMODE, Pdo::ERRMODE_EXCEPTION);
        $actual = $pdo->getAttribute(Pdo::ATTR_ERRMODE);
        $this->assertSame(Pdo::ERRMODE_EXCEPTION, $actual);
        
        // set quote-name attr correctly, although the quote itself is wrong
        $pdo->setAttribute(Pdo::ATTR_QUOTE_NAME_PREFIX, '{');
        $pdo->setAttribute(Pdo::ATTR_QUOTE_NAME_SUFFIX, '}');
        $this->assertSame('{', $pdo->getAttribute(Pdo::ATTR_QUOTE_NAME_PREFIX));
        $this->assertSame('}', $pdo->getAttribute(Pdo::ATTR_QUOTE_NAME_SUFFIX));
        
        // set quote-name attr incorrectly
        $this->setExpectedException('Aura\Sql\Exception\AttrQuoteNameEmpty');
        $pdo->setAttribute(Pdo::ATTR_QUOTE_NAME_PREFIX, ' ');
    }
    
    public function testQuery()
    {
        $stm = "SELECT * FROM pdotest";
        $sth = $this->pdo->query($stm);
        $this->assertInstanceOf('PDOStatement', $sth);
        $result = $sth->fetchAll(Pdo::FETCH_ASSOC);
        $expect = 10;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
    }
    
    public function testBindValues()
    {
        $expect = array('foo' => 'bar', 'baz' => 'dib');
        $this->pdo->bindValues($expect);
        $actual = $this->pdo->getBindValues();
        $this->assertSame($expect, $actual);
    }
    
    public function testQueryWithBindValues()
    {
        $stm = "SELECT * FROM pdotest WHERE id <= :val";
        $this->pdo->bindValues(array('val' => '5'));
        $sth = $this->pdo->query($stm);
        $this->assertInstanceOf('PDOStatement', $sth);
        $result = $sth->fetchAll(Pdo::FETCH_ASSOC);
        $expect = 5;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
    }
    
    public function testQueryWithArrayValues()
    {
        $stm = "SELECT * FROM pdotest WHERE id IN (:list) OR id = :id";
        
        $this->pdo->bindValues(array(
            'list' => array(1, 2, 3, 4),
            'id' => 5
        ));
        
        $sth = $this->pdo->query($stm);
        $this->assertInstanceOf('PDOStatement', $sth);
        
        $result = $sth->fetchAll(Pdo::FETCH_ASSOC);
        $expect = 5;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
    }
    
    public function testQueryWithFetchMode()
    {
        $stm = "SELECT id, name FROM pdotest";
        
        // mode and 2 args
        $sth = $this->pdo->query($stm, Pdo::FETCH_CLASS, 'StdClass', array());
        $actual = $sth->fetchAll();
        $expect = array(
            0 => (object) array(
               'id' => '1',
               'name' => 'Anna',
            ),
            1 => (object) array(
               'id' => '2',
               'name' => 'Betty',
            ),
            2 => (object) array(
               'id' => '3',
               'name' => 'Clara',
            ),
            3 => (object) array(
               'id' => '4',
               'name' => 'Donna',
            ),
            4 => (object) array(
               'id' => '5',
               'name' => 'Fiona',
            ),
            5 => (object) array(
               'id' => '6',
               'name' => 'Gertrude',
            ),
            6 => (object) array(
               'id' => '7',
               'name' => 'Hanna',
            ),
            7 => (object) array(
               'id' => '8',
               'name' => 'Ione',
            ),
            8 => (object) array(
               'id' => '9',
               'name' => 'Julia',
            ),
            9 => (object) array(
               'id' => '10',
               'name' => 'Kara',
            ),
        );
        $this->assertEquals($expect, $actual);
        
        // mode and 1 arg
        $sth = $this->pdo->query($stm, Pdo::FETCH_COLUMN, 1);
        $actual = $sth->fetchAll();
        $expect = array(
            0 => 'Anna',
            1 => 'Betty',
            2 => 'Clara',
            3 => 'Donna',
            4 => 'Fiona',
            5 => 'Gertrude',
            6 => 'Hanna',
            7 => 'Ione',
            8 => 'Julia',
            9 => 'Kara',
        );
        $this->assertSame($actual, $expect);
        
        // mode only
        $sth = $this->pdo->query($stm, Pdo::FETCH_ASSOC);
        $actual = $sth->fetchAll();
        $expect = array(
            0 => array(
               'id' => '1',
               'name' => 'Anna',
            ),
            1 => array(
               'id' => '2',
               'name' => 'Betty',
            ),
            2 => array(
               'id' => '3',
               'name' => 'Clara',
            ),
            3 => array(
               'id' => '4',
               'name' => 'Donna',
            ),
            4 => array(
               'id' => '5',
               'name' => 'Fiona',
            ),
            5 => array(
               'id' => '6',
               'name' => 'Gertrude',
            ),
            6 => array(
               'id' => '7',
               'name' => 'Hanna',
            ),
            7 => array(
               'id' => '8',
               'name' => 'Ione',
            ),
            8 => array(
               'id' => '9',
               'name' => 'Julia',
            ),
            9 => array(
               'id' => '10',
               'name' => 'Kara',
            ),
        );
        $this->assertEquals($expect, $actual);
        
    }
    
    public function testPrepareWithQuotedStringsAndData()
    {
        $stm = "SELECT * FROM pdotest
                 WHERE 'leave '':foo'' alone'
                 AND id IN (:list)
                 AND \"leave '':bar' alone\"";
        
        $this->pdo->bindValues(array(
            'list' => array('1', '2', '3', '4', '5'),
            'foo' => 'WRONG',
            'bar' => 'WRONG',
        ));
        
        $sth = $this->pdo->prepare($stm);
        
        $expect = str_replace(':list', "'1', '2', '3', '4', '5'", $stm);
        $actual = $sth->queryString;
        $this->assertSame($expect, $actual);
    }
    
    public function testFetchAll()
    {
        $stm = "SELECT * FROM pdotest";
        $result = $this->pdo->fetchAll($stm);
        $expect = 10;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
    }
    
    public function testFetchAssoc()
    {
        $stm = "SELECT * FROM pdotest ORDER BY id";
        $result = $this->pdo->fetchAssoc($stm);
        $expect = 10;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
        
        // 1-based IDs, not 0-based sequential values
        $expect = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
        $actual = array_keys($result);
        $this->assertEquals($expect, $actual);
    }
    
    public function testFetchCol()
    {
        $stm = "SELECT id FROM pdotest ORDER BY id";
        $result = $this->pdo->fetchCol($stm);
        $expect = 10;
        $actual = count($result);
        $this->assertEquals($expect, $actual);
        
        // // 1-based IDs, not 0-based sequential values
        $expect = array('1', '2', '3', '4', '5', '6', '7', '8', '9', '10');
        $this->assertEquals($expect, $result);
    }
    
    public function testFetchOne()
    {
        $stm = "SELECT id, name FROM pdotest WHERE id = 1";
        $actual = $this->pdo->fetchOne($stm);
        $expect = array(
            'id'   => '1',
            'name' => 'Anna',
        );
        $this->assertEquals($expect, $actual);
    }
    
    public function testFetchPairs()
    {
        $stm = "SELECT id, name FROM pdotest ORDER BY id";
        $actual = $this->pdo->fetchPairs($stm);
        $expect = array(
          1  => 'Anna',
          2  => 'Betty',
          3  => 'Clara',
          4  => 'Donna',
          5  => 'Fiona',
          6  => 'Gertrude',
          7  => 'Hanna',
          8  => 'Ione',
          9  => 'Julia',
          10 => 'Kara',
        );
        $this->assertEquals($expect, $actual);
    }
    
    public function testFetchValue()
    {
        $stm = "SELECT id FROM pdotest WHERE id = 1";
        $actual = $this->pdo->fetchValue($stm);
        $expect = '1';
        $this->assertEquals($expect, $actual);
    }
    
    public function testQuote()
    {
        // quote a string
        $actual = $this->pdo->quote('"foo" bar \'baz\'');
        $this->assertEquals("'\"foo\" bar ''baz'''", $actual);
        
        // quote an integer
        $actual = $this->pdo->quote(123);
        $this->assertEquals("'123'", $actual);
        
        // quote a float
        $actual = $this->pdo->quote(123.456);
        $this->assertEquals("'123.456'", $actual);
        
        // quote an array
        $actual = $this->pdo->quote(array('"foo"', 'bar', "'baz'"));
        $this->assertEquals( "'\"foo\"', 'bar', '''baz'''", $actual);
    }
    
    public function testLastInsertId()
    {
        $cols = array('name' => 'Laura');
        $this->insert($cols);
        $expect = 11;
        $actual = $this->pdo->lastInsertId();
        $this->assertEquals($expect, $actual);
    }
    
    public function testTransactions()
    {
        // data
        $cols = array('name' => 'Laura');
    
        // begin and rollback
        $this->assertFalse($this->pdo->inTransaction());
        $this->pdo->beginTransaction();
        $this->assertTrue($this->pdo->inTransaction());
        $this->insert($cols);
        $actual = $this->pdo->fetchAll("SELECT * FROM pdotest");
        $this->assertSame(11, count($actual));
        $this->pdo->rollback();
        $this->assertFalse($this->pdo->inTransaction());
        
        $actual = $this->pdo->fetchAll("SELECT * FROM pdotest");
        $this->assertSame(10, count($actual));
        
        // begin and commit
        $this->assertFalse($this->pdo->inTransaction());
        $this->pdo->beginTransaction();
        $this->assertTrue($this->pdo->inTransaction());
        $this->insert($cols);
        $this->pdo->commit();
        $this->assertFalse($this->pdo->inTransaction());
        
        $actual = $this->pdo->fetchAll("SELECT * FROM pdotest");
        $this->assertSame(11, count($actual));
    }
    
    public function testProfiling()
    {
        $this->pdo->setProfiler(new Profiler);
        
        // leave inactive
        $this->pdo->query("SELECT 1 FROM pdotest");
        $profiles = $this->pdo->getProfiler()->getProfiles();
        $this->assertEquals(0, count($profiles));
        
        // activate
        $this->pdo->getProfiler()->setActive(true);
        
        $this->pdo->bindValues(array('foo' => 'bar'));
        $this->pdo->query("SELECT 1 FROM pdotest");
        
        $this->pdo->bindValues(array('baz' => 'dib'));
        $this->pdo->exec("SELECT 2 FROM pdotest");
        
        $this->pdo->bindValues(array('zim' => 'gir'));
        $this->pdo->fetchAll("SELECT 3 FROM pdotest");
        
        $profiles = $this->pdo->getProfiler()->getProfiles();
        $this->assertEquals(3, count($profiles));
        
        // get the profiles, remove stuff that's variable
        $actual = $this->pdo->getProfiler()->getProfiles();
        foreach ($actual as $key => $val) {
            unset($actual[$key]['duration']);
            unset($actual[$key]['trace']);
        }
        
        $expect = array(
            0 => array(
                'function' => 'query',
                'statement' => 'SELECT 1 FROM pdotest',
                'bind_values' => array(
                    'foo' => 'bar',
                ),
            ),
            1 => array(
                'function' => 'exec',
                'statement' => 'SELECT 2 FROM pdotest',
                'bind_values' => array(
                    'baz' => 'dib',
                ),
            ),
            2 => array(
                'function' => 'query',
                'statement' => 'SELECT 3 FROM pdotest',
                'bind_values' => array(
                    'zim' => 'gir',
                ),
            ),
        );
        
        $this->assertSame($expect, $actual);
    }

    public function testQuoteValuesIn()
    {
        // no placeholders
        $actual = $this->pdo->quoteReplace('foo = bar', "'zim'");
        $expect = 'foo = bar';
        $this->assertEquals($expect, $actual);
        
        // one placeholder, one value
        $actual = $this->pdo->quoteReplace("foo = ?", "'bar'");
        $this->assertEquals($this->expect_quote_into,$actual);
        
        // many placeholders, many values
        $actual = $this->pdo->quoteReplace("foo = ? AND zim = ?", array("'bar'", "'baz'"));
        $this->assertEquals($this->expect_quote_values_in, $actual);
        
        // many placeholders, too many values
        $actual = $this->pdo->quoteReplace("foo = ? AND zim = ?", array("'bar'", "'baz'", "'gir'"));
        $this->assertEquals($this->expect_quote_values_in, $actual);
    }
    
    public function testQuoteName()
    {
        // table AS alias
        $actual = $this->pdo->quoteName('table AS alias');
        $this->assertEquals($this->expect_quote_name_table_as_alias, $actual);
        
        // table.col AS alias
        $actual = $this->pdo->quoteName('table.col AS alias');
        $this->assertEquals($this->expect_quote_name_table_col_as_alias, $actual);
        
        // table alias
        $actual = $this->pdo->quoteName('table alias');
        $this->assertEquals($this->expect_quote_name_table_alias, $actual);
        
        // table.col alias
        $actual = $this->pdo->quoteName('table.col alias');
        $this->assertEquals($this->expect_quote_name_table_col_alias, $actual);
        
        // plain old identifier
        $actual = $this->pdo->quoteName('table');
        $this->assertEquals($this->expect_quote_name_plain, $actual);
        
        // star
        $actual = $this->pdo->quoteName('*');
        $this->assertEquals('*', $actual);
        
        // star dot star
        $actual = $this->pdo->quoteName('*.*');
        $this->assertEquals('*.*', $actual);
    }
    
    public function testQuoteNamesIn()
    {
        $sql = "*, *.*, foo.bar, CONCAT('foo.bar', \"baz.dib\") AS zim";
        $actual = $this->pdo->quoteNamesIn($sql);
        $this->assertEquals($this->expect_quote_names_in, $actual);
    }
}
