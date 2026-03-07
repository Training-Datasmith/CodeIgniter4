<?php

declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_blog extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field([
            'blog_id' => [
                'type'           => 'INT',
                'constraint'     => 5,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'blog_title' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'blog_description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
        $this->dbforge->add_key('blog_id', true);
        $this->dbforge->create_table('blog');
    }

    public function down()
    {
        $this->dbforge->drop_table('blog');
    }
}
