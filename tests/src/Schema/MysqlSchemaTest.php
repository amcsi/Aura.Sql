<?php
namespace Aura\Sql\Schema;

class MysqlSchemaTest extends AbstractSchemaTest
{
    protected $extension = 'pdo_mysql';
    
    protected $connection_type = 'mysql';
    
    protected $expect_fetch_table_list = ['aura_test_table'];
    
    protected $expect_fetch_table_list_schema = ['aura_test_table'];
    
    protected $expect_fetch_table_cols = [
        'id' => [
            'name' => 'id',
            'type' => 'int',
            'size' => 11,
            'scale' => null,
            'default' => null,
            'notnull' => true,
            'primary' => true,
            'autoinc' => true,
        ],
        'name' => [
            'name' => 'name',
            'type' => 'varchar',
            'size' => 50,
            'scale' => null,
            'default' => null,
            'notnull' => true,
            'primary' => false,
            'autoinc' => false,
        ],
        'test_size_scale' => [
            'name' => 'test_size_scale',
            'type' => 'decimal',
            'size' => 7,
            'scale' => 3,
            'default' => null,
            'notnull' => false,
            'primary' => false,
            'autoinc' => false,
        ],
        'test_default_null' => [
            'name' => 'test_default_null',
            'type' => 'char',
            'size' => 3,
            'scale' => null,
            'default' => null,
            'notnull' => false,
            'primary' => false,
            'autoinc' => false,
        ],
        'test_default_string' => [
            'name' => 'test_default_string',
            'type' => 'varchar',
            'size' => 7,
            'scale' => null,
            'default' => 'string',
            'notnull' => false,
            'primary' => false,
            'autoinc' => false,
        ],
        'test_default_number' => [
            'name' => 'test_default_number',
            'type' => 'decimal',
            'size' => 5,
            'scale' => null,
            'default' => '12345',
            'notnull' => false,
            'primary' => false,
            'autoinc' => false,
        ],
        'test_default_ignore' => [
            'name' => 'test_default_ignore',
            'type' => 'timestamp',
            'size' => null,
            'scale' => null,
            'default' => null,
            'notnull' => true,
            'primary' => false,
            'autoinc' => false,
        ],
    ];
    
}