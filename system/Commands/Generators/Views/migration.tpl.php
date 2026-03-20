<@php

namespace {namespace};

use CodeIgniter\Database\Migration;

class {class} extends Migration
{
<?php 
if ($session) {
    ?>
    protected $DBGroup = '<?php 
    echo $db_group;
    ?>';

    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => false],
<?php 
    if ($db_driver === 'MySQLi') {
        ?>
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => false],
            '`timestamp` timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL',
            'data' => ['type' => 'BLOB', 'null' => false],
 <?php 
    } elseif ($db_driver === 'Postgre') {
        ?>
            'ip_address inet NOT NULL',
            'timestamp timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL',
            "data bytea DEFAULT '' NOT NULL",
<?php 
    }
    ?>
        ]);
<?php 
    if ($match_ip) {
        ?>
        $this->forge->addKey(['id', 'ip_address'], true);
<?php 
    } else {
        ?>
        $this->forge->addKey('id', true);
<?php 
    }
    ?>
        $this->forge->addKey('timestamp');
        $this->forge->createTable('<?php 
    echo $table;
    ?>', true);
    }

    public function down()
    {
        $this->forge->dropTable('<?php 
    echo $table;
    ?>', true);
    }
<?php 
} else {
    ?>
    public function up()
    {
        //
    }

    public function down()
    {
        //
    }
<?php 
}
?>
}
