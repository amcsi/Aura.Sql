<?php
namespace Aura\Sql\Schema;

class SqlsrvSchema extends AbstractSchema
{
    /**
     * 
     * Constructor.
     * 
     * @param PdoInterface $connection A database connection.
     * 
     * @param ColumnFactory $column_factory A column object factory.
     * 
     */
    public function __construct(
        PdoSqlsrv $pdo,
        ColumnFactory $column_factory
    ) {
        $this->pdo = $pdo;
        $this->column_factory = $column_factory;
    }
    
    /**
     * 
     * Returns a list of all tables in the database.
     * 
     * @param string $schema Fetch tbe list of tables in this schema; 
     * when empty, uses the default schema.
     * 
     * @return array All table names in the database.
     * 
     * @todo Honor the $schema param.
     * 
     */
    public function fetchTableList($schema = null)
    {
        $text = "SELECT name FROM sysobjects WHERE type = 'U' ORDER BY name";
        return $this->pdo->fetchCol($text);
    }

    /**
     * 
     * Returns an array of columns in a table.
     * 
     * @param string $spec Return the columns in this table. This may be just
     * a `table` name, or a `schema.table` name.
     * 
     * @return array An associative array where the key is the column name
     * and the value is a Column object.
     * 
     * @todo Honor `schema.table` as the specification.
     * 
     */
    public function fetchTableCols($spec)
    {
        list($schema, $table) = $this->splitName($spec);

        // get column info
        $text = "exec sp_columns @table_name = " . $this->pdo->quoteName($table);
        $raw_cols = $this->pdo->fetchAll($text);

        // get primary key info
        $text = "exec sp_pkeys @table_owner = " . $raw_cols[0]['TABLE_OWNER']
              . ", @table_name = " . $this->pdo->quoteName($table);
        $raw_keys = $this->pdo->fetchAll($text);
        $keys = [];
        foreach ($raw_keys as $row) {
            $keys[] = $row['COLUMN_NAME'];
        }

        $cols = [];
        foreach ($raw_cols as $row) {

            $name = $row['COLUMN_NAME'];

            $pos = strpos($row['TYPE_NAME'], ' ');
            if ($pos === false) {
                $type = $row['TYPE_NAME'];
            } else {
                $type = substr($row['TYPE_NAME'], 0, $pos);
            }

            // save the column description
            $cols[$name] = $this->column_factory->newInstance(
                $name,
                $type,
                $row['PRECISION'],
                $row['SCALE'],
                ! $row['NULLABLE'],
                $row['COLUMN_DEF'],
                strpos(strtolower($row['TYPE_NAME']), 'identity') !== false,
                in_array($name, $keys)
            );
        }

        return $cols;
    }
}
