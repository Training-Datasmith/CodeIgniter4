<?php

declare (strict_types=1);
/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */
namespace Code_Igniter\Database;

use Config\Database;
/**
 * Class Migration
 */
abstract class Migration
{
    /**
     * The name of the database group to use.
     *
     * @var string|null
     */
    protected $db_group;
    /**
     * Database Connection instance
     *
     * @var ConnectionInterface
     */
    protected $db;
    /**
     * Database Forge instance.
     *
     * @var Forge
     */
    protected $forge;
    public function __construct(?Forge $forge = null)
    {
        if (isset($this->db_group)) {
            $this->forge = Database::forge($this->db_group);
        } elseif ($forge instanceof Forge) {
            $this->forge = $forge;
        } else {
            $this->forge = Database::forge(config(Database::class)->default_group);
        }
        $this->db = $this->forge->get_connection();
    }
    /**
     * Returns the database group name this migration uses.
     */
    public function get_db_group(): ?string
    {
        return $this->db_group;
    }
    /**
     * Perform a migration step.
     *
     * @return void
     */
    abstract public function up();
    /**
     * Revert a migration step.
     *
     * @return void
     */
    abstract public function down();
}