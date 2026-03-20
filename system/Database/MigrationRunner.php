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

use Code_Igniter\CLI\CLI;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Events\Events;
use Code_Igniter\Exceptions\Config_Exception;
use Code_Igniter\Exceptions\RuntimeException;
use Code_Igniter\I18n\Time;
use Config\Database;
use Config\Migrations as MigrationsConfig;
use stdClass;
/**
 * Class MigrationRunner
 */
class Migration_Runner
{
    /**
     * Whether or not migrations are allowed to run.
     *
     * @var bool
     */
    protected $enabled = false;
    /**
     * Name of table to store meta information
     *
     * @var string
     */
    protected $table;
    /**
     * The Namespace where migrations can be found.
     * `null` is all namespaces.
     *
     * @var string|null
     */
    protected $namespace = APP_NAMESPACE;
    /**
     * The database Group to migrate.
     *
     * @var string
     */
    protected $group;
    /**
     * The migration name.
     *
     * @var string
     */
    protected $name;
    /**
     * The pattern used to locate migration file versions.
     *
     * @var string
     */
    protected $regex = '/\A(\d{4}[_-]?\d{2}[_-]?\d{2}[_-]?\d{6})_(\w+)\z/';
    /**
     * The main database connection. Used to store
     * migration information in.
     *
     * @var BaseConnection
     */
    protected $db;
    /**
     * If true, will continue instead of throwing
     * exceptions.
     *
     * @var bool
     */
    protected $silent = false;
    /**
     * used to return messages for CLI.
     *
     * @var array
     */
    protected $cli_messages = [];
    /**
     * Tracks whether we have already ensured
     * the table exists or not.
     *
     * @var bool
     */
    protected $table_checked = false;
    /**
     * Lock the migration table.
     */
    protected bool $lock = false;
    /**
     * Tracks whether we have already ensured
     * the lock table exists or not.
     */
    protected bool $lock_table_checked = false;
    /**
     * The full path to locate migration files.
     *
     * @var string
     */
    protected $path;
    /**
     * The database Group filter.
     *
     * @var string|null
     */
    protected $group_filter;
    /**
     * Used to skip current migration.
     *
     * @var bool
     */
    protected $group_skip = false;
    /**
     * The migration can manage multiple databases. So it should always use the
     * default DB group so that it creates the `migrations` table in the default
     * DB group. Therefore, passing $db is for testing purposes only.
     *
     * @param array|ConnectionInterface|string|null $db DB group. For testing purposes only.
     *
     * @throws ConfigException
     */
    public function __construct(Migrations_Config $config, $db = null)
    {
        $this->enabled = $config->enabled ?? false;
        $this->table = $config->table ?? 'migrations';
        $this->lock = $config->lock ?? false;
        // Even if a DB connection is passed, since it is a test,
        // it is assumed to use the default group name
        $this->group = is_string($db) ? $db : config(Database::class)->default_group;
        $this->db = db_connect($db);
    }
    /**
     * Locate and run all new migrations
     *
     * @return bool
     *
     * @throws ConfigException
     * @throws RuntimeException
     */
    public function latest(?string $group = null)
    {
        if (!$this->enabled) {
            throw Config_Exception::for_disabled_migrations();
        }
        $this->ensure_table();
        // Try to acquire lock - exit gracefully if another process is running migrations
        if ($this->lock && !$this->acquire_migration_lock()) {
            $message = lang('Migrations.locked');
            $this->cli_messages[] = "\t" . CLI::color($message, 'yellow');
            return true;
        }
        try {
            if ($group !== null) {
                $this->group_filter = $group;
                $this->set_group($group);
            }
            $migrations = $this->find_migrations();
            if ($migrations === []) {
                return true;
            }
            foreach ($this->get_history((string) $group) as $history) {
                unset($migrations[$this->get_object_uid($history)]);
            }
            $batch = $this->get_last_batch() + 1;
            foreach ($migrations as $migration) {
                if ($this->migrate('up', $migration)) {
                    if ($this->group_skip === true) {
                        $this->group_skip = false;
                        continue;
                    }
                    $this->add_history($migration, $batch);
                } else {
                    $this->regress(-1);
                    $message = lang('Migrations.generalFault');
                    if ($this->silent) {
                        $this->cli_messages[] = "\t" . CLI::color($message, 'red');
                        return false;
                    }
                    throw new RuntimeException($message);
                }
            }
            $data = get_object_vars($this);
            $data['method'] = 'latest';
            Events::trigger('migrate', $data);
            return true;
        } finally {
            if ($this->lock) {
                $this->release_migration_lock();
            }
        }
    }
    /**
     * Migrate down to a previous batch
     *
     * Calls each migration step required to get to the provided batch
     *
     * @param int         $targetBatch Target batch number, or negative for a relative batch, 0 for all
     * @param string|null $group       Deprecated. The designation has no effect.
     *
     * @return bool True on success, FALSE on failure or no migrations are found
     *
     * @throws ConfigException
     * @throws RuntimeException
     */
    public function regress(int $target_batch = 0, ?string $group = null)
    {
        if (!$this->enabled) {
            throw Config_Exception::for_disabled_migrations();
        }
        $this->ensure_table();
        // Try to acquire lock - exit gracefully if another process is running migrations
        if ($this->lock && !$this->acquire_migration_lock()) {
            $message = lang('Migrations.locked');
            $this->cli_messages[] = "\t" . CLI::color($message, 'yellow');
            return true;
        }
        try {
            $batches = $this->get_batches();
            if ($target_batch < 0) {
                $target_batch = $batches[count($batches) - 1 + $target_batch] ?? 0;
            }
            if ($batches === [] && $target_batch === 0) {
                return true;
            }
            if ($target_batch !== 0 && !in_array($target_batch, $batches, true)) {
                $message = lang('Migrations.batchNotFound') . $target_batch;
                if ($this->silent) {
                    $this->cli_messages[] = "\t" . CLI::color($message, 'red');
                    return false;
                }
                throw new RuntimeException($message);
            }
            $tmp_namespace = $this->namespace;
            $this->namespace = null;
            $all_migrations = $this->find_migrations();
            $migrations = [];
            while ($batch = array_pop($batches)) {
                if ($batch <= $target_batch) {
                    break;
                }
                foreach ($this->get_batch_history($batch, 'desc') as $history) {
                    $uid = $this->get_object_uid($history);
                    if (!isset($all_migrations[$uid])) {
                        $message = lang('Migrations.gap') . ' ' . $history->version;
                        if ($this->silent) {
                            $this->cli_messages[] = "\t" . CLI::color($message, 'red');
                            return false;
                        }
                        throw new RuntimeException($message);
                    }
                    $migration = $all_migrations[$uid];
                    $migration->history = $history;
                    $migrations[] = $migration;
                }
            }
            foreach ($migrations as $migration) {
                if ($this->migrate('down', $migration)) {
                    $this->remove_history($migration->history);
                } else {
                    $message = lang('Migrations.generalFault');
                    if ($this->silent) {
                        $this->cli_messages[] = "\t" . CLI::color($message, 'red');
                        return false;
                    }
                    throw new RuntimeException($message);
                }
            }
            $data = get_object_vars($this);
            $data['method'] = 'regress';
            Events::trigger('migrate', $data);
            $this->namespace = $tmp_namespace;
            return true;
        } finally {
            if ($this->lock) {
                $this->release_migration_lock();
            }
        }
    }
    /**
     * Migrate a single file regardless of order or batches.
     * Method "up" or "down" determined by presence in history.
     * NOTE: This is not recommended and provided mostly for testing.
     *
     * @param string $path Full path to a valid migration file
     * @param string $path Namespace of the target migration
     *
     * @return bool
     */
    public function force(string $path, string $namespace, ?string $group = null)
    {
        if (!$this->enabled) {
            throw Config_Exception::for_disabled_migrations();
        }
        $this->ensure_table();
        // Try to acquire lock - exit gracefully if another process is running migrations
        if ($this->lock && !$this->acquire_migration_lock()) {
            $message = lang('Migrations.locked');
            $this->cli_messages[] = "\t" . CLI::color($message, 'yellow');
            return true;
        }
        try {
            if ($group !== null) {
                $this->group_filter = $group;
                $this->set_group($group);
            }
            $migration = $this->migration_from_file($path, $namespace);
            if ($migration === false) {
                $message = lang('Migrations.notFound');
                if ($this->silent) {
                    $this->cli_messages[] = "\t" . CLI::color($message, 'red');
                    return false;
                }
                throw new RuntimeException($message);
            }
            $method = 'up';
            $this->set_namespace($migration->namespace);
            foreach ($this->get_history($this->group) as $history) {
                if ($this->get_object_uid($history) === $migration->uid) {
                    $method = 'down';
                    $migration->history = $history;
                    break;
                }
            }
            if ($method === 'up') {
                $batch = $this->get_last_batch() + 1;
                if ($this->migrate('up', $migration) && $this->group_skip === false) {
                    $this->add_history($migration, $batch);
                    return true;
                }
                $this->group_skip = false;
            } elseif ($this->migrate('down', $migration)) {
                $this->remove_history($migration->history);
                return true;
            }
            $message = lang('Migrations.generalFault');
            if ($this->silent) {
                $this->cli_messages[] = "\t" . CLI::color($message, 'red');
                return false;
            }
            throw new RuntimeException($message);
        } finally {
            if ($this->lock) {
                $this->release_migration_lock();
            }
        }
    }
    /**
     * Retrieves list of available migration scripts
     *
     * @return array List of all located migrations by their UID
     */
    public function find_migrations(): array
    {
        $namespaces = $this->namespace !== null ? [$this->namespace] : array_keys(service('autoloader')->get_namespace());
        $migrations = [];
        foreach ($namespaces as $namespace) {
            if (ENVIRONMENT !== 'testing' && $namespace === 'Tests\Support') {
                continue;
            }
            foreach ($this->find_namespace_migrations($namespace) as $migration) {
                $migrations[$migration->uid] = $migration;
            }
        }
        // Sort migrations ascending by their UID (version)
        ksort($migrations);
        return $migrations;
    }
    /**
     * Retrieves a list of available migration scripts for one namespace
     */
    public function find_namespace_migrations(string $namespace): array
    {
        $migrations = [];
        $locator = service('locator', true);
        if (!empty($this->path)) {
            helper('filesystem');
            $dir = rtrim($this->path, DIRECTORY_SEPARATOR) . '/';
            $files = get_filenames($dir, true, false, false);
        } else {
            $files = $locator->list_namespace_files($namespace, '/Database/Migrations/');
        }
        foreach ($files as $file) {
            $file = empty($this->path) ? $file : $this->path . str_replace($this->path, '', $file);
            if ($migration = $this->migration_from_file($file, $namespace)) {
                $migrations[] = $migration;
            }
        }
        return $migrations;
    }
    /**
     * Create a migration object from a file path.
     *
     * @param string $path Full path to a valid migration file.
     *
     * @return false|object Returns the migration object, or false on failure
     */
    protected function migration_from_file(string $path, string $namespace)
    {
        if (!str_ends_with($path, '.php')) {
            return false;
        }
        $filename = basename($path, '.php');
        if (preg_match($this->regex, $filename) !== 1) {
            return false;
        }
        $locator = service('locator', true);
        $migration = new stdClass();
        $migration->version = $this->get_migration_number($filename);
        $migration->name = $this->get_migration_name($filename);
        $migration->path = $path;
        $migration->class = $locator->get_classname($path);
        $migration->namespace = $namespace;
        $migration->uid = $this->get_object_uid($migration);
        return $migration;
    }
    /**
     * Allows other scripts to modify on the fly as needed.
     *
     * @return MigrationRunner
     */
    public function set_namespace(?string $namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }
    /**
     * Allows other scripts to modify on the fly as needed.
     *
     * @return MigrationRunner
     */
    public function set_group(string $group)
    {
        $this->group = $group;
        return $this;
    }
    /**
     * @return MigrationRunner
     */
    public function set_name(string $name)
    {
        $this->name = $name;
        return $this;
    }
    /**
     * If $silent == true, then will not throw exceptions and will
     * attempt to continue gracefully.
     *
     * @return MigrationRunner
     */
    public function set_silent(bool $silent)
    {
        $this->silent = $silent;
        return $this;
    }
    /**
     * Extracts the migration number from a filename
     *
     * @param string $migration A migration filename w/o path.
     */
    protected function get_migration_number(string $migration): string
    {
        preg_match($this->regex, $migration, $matches);
        return $matches !== [] ? $matches[1] : '0';
    }
    /**
     * Extracts the migration name from a filename
     *
     * Note: The migration name should be the classname, but maybe they are
     *       different.
     *
     * @param string $migration A migration filename w/o path.
     */
    protected function get_migration_name(string $migration): string
    {
        preg_match($this->regex, $migration, $matches);
        return $matches !== [] ? $matches[2] : '';
    }
    /**
     * Uses the non-repeatable portions of a migration or history
     * to create a sortable unique key
     *
     * @param object $object migration or $history
     */
    public function get_object_uid($object): string
    {
        return preg_replace('/[^0-9]/', '', $object->version) . $object->class;
    }
    /**
     * Retrieves messages formatted for CLI output
     */
    public function get_cli_messages(): array
    {
        return $this->cli_messages;
    }
    /**
     * Clears any CLI messages.
     *
     * @return MigrationRunner
     */
    public function clear_cli_messages()
    {
        $this->cli_messages = [];
        return $this;
    }
    /**
     * Truncates the history table.
     *
     * @return void
     */
    public function clear_history()
    {
        if ($this->db->table_exists($this->table)) {
            $this->db->table($this->table)->truncate();
        }
    }
    /**
     * Add a history to the table.
     *
     * @param object $migration
     *
     * @return void
     */
    protected function add_history($migration, int $batch)
    {
        $this->db->table($this->table)->insert(['version' => $migration->version, 'class' => $migration->class, 'group' => $this->group, 'namespace' => $migration->namespace, 'time' => Time::now()->get_timestamp(), 'batch' => $batch]);
        if (is_cli()) {
            $this->cli_messages[] = sprintf("\t%s(%s) %s_%s", CLI::color(lang('Migrations.added'), 'yellow'), $migration->namespace, $migration->version, $migration->class);
        }
    }
    /**
     * Removes a single history
     *
     * @param object $history
     *
     * @return void
     */
    protected function remove_history($history)
    {
        $this->db->table($this->table)->where('id', $history->id)->delete();
        if (is_cli()) {
            $this->cli_messages[] = sprintf("\t%s(%s) %s_%s", CLI::color(lang('Migrations.removed'), 'yellow'), $history->namespace, $history->version, $history->class);
        }
    }
    /**
     * Grabs the full migration history from the database for a group
     */
    public function get_history(string $group = 'default'): array
    {
        $this->ensure_table();
        $builder = $this->db->table($this->table);
        // If group was specified then use it
        if ($group !== '') {
            $builder->where('group', $group);
        }
        // If a namespace was specified then use it
        if ($this->namespace !== null) {
            $builder->where('namespace', $this->namespace);
        }
        $query = $builder->order_by('id', 'ASC')->get();
        return empty($query) ? [] : $query->get_result_object();
    }
    /**
     * Returns the migration history for a single batch.
     *
     * @param string $order
     */
    public function get_batch_history(int $batch, $order = 'asc'): array
    {
        $this->ensure_table();
        $query = $this->db->table($this->table)->where('batch', $batch)->order_by('id', $order)->get();
        return empty($query) ? [] : $query->get_result_object();
    }
    /**
     * Returns all the batches from the database history in order
     */
    public function get_batches(): array
    {
        $this->ensure_table();
        $batches = $this->db->table($this->table)->select('batch')->distinct()->order_by('batch', 'asc')->get()->get_result_array();
        return array_map(intval(...), array_column($batches, 'batch'));
    }
    /**
     * Returns the value of the last batch in the database.
     */
    public function get_last_batch(): int
    {
        $this->ensure_table();
        $batch = $this->db->table($this->table)->select_max('batch')->get()->get_result_object();
        $batch = is_array($batch) && $batch !== [] ? end($batch)->batch : 0;
        return (int) $batch;
    }
    /**
     * Returns the version number of the first migration for a batch.
     * Mostly just for tests.
     */
    public function get_batch_start(int $batch): string
    {
        if ($batch < 0) {
            $batches = $this->get_batches();
            $batch = $batches[count($batches) - 1] ?? 0;
        }
        $migration = $this->db->table($this->table)->where('batch', $batch)->order_by('id', 'asc')->limit(1)->get()->get_result_object();
        return $migration !== [] ? $migration[0]->version : '0';
    }
    /**
     * Returns the version number of the last migration for a batch.
     * Mostly just for tests.
     */
    public function get_batch_end(int $batch): string
    {
        if ($batch < 0) {
            $batches = $this->get_batches();
            $batch = $batches[count($batches) - 1] ?? 0;
        }
        $migration = $this->db->table($this->table)->where('batch', $batch)->order_by('id', 'desc')->limit(1)->get()->get_result_object();
        return $migration === [] ? '0' : $migration[0]->version;
    }
    /**
     * Ensures that we have created our migrations table
     * in the database.
     *
     * @return void
     */
    public function ensure_table()
    {
        if ($this->table_checked || $this->db->table_exists($this->table)) {
            return;
        }
        $forge = Database::forge($this->db);
        $forge->add_field(['id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true], 'version' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false], 'class' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false], 'group' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false], 'namespace' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false], 'time' => ['type' => 'INT', 'constraint' => 11, 'null' => false], 'batch' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false]]);
        $forge->add_primary_key('id');
        $forge->create_table($this->table, true);
        $this->table_checked = true;
    }
    /**
     * Ensures that we have created our migration
     * lock table in the database.
     *
     * @return string The lock table name
     */
    protected function ensure_lock_table(): string
    {
        $lock_table = $this->table . '_lock';
        if ($this->lock_table_checked || $this->db->table_exists($lock_table)) {
            $this->lock_table_checked = true;
            return $lock_table;
        }
        $forge = Database::forge($this->db);
        $forge->add_field(['id' => ['type' => 'BIGINT', 'auto_increment' => true], 'lock_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false, 'unique' => true], 'acquired_at' => ['type' => 'INTEGER', 'null' => false]]);
        $forge->add_primary_key('id');
        $forge->create_table($lock_table, true);
        $this->lock_table_checked = true;
        return $lock_table;
    }
    /**
     * Acquire exclusive lock on migrations to prevent concurrent execution
     *
     * @return bool True if lock was acquired, false if another process holds the lock
     */
    protected function acquire_migration_lock(): bool
    {
        $lock_table = $this->ensure_lock_table();
        try {
            $this->db->table($lock_table)->insert(['lock_name' => 'migration_process', 'acquired_at' => Time::now()->get_timestamp()]);
            return $this->db->insert_id() > 0;
        } catch (Database_Exception) {
            // Lock already exists or other error
            return false;
        }
    }
    /**
     * Release migration lock
     *
     * @return bool True if successfully released, false on error
     */
    protected function release_migration_lock(): bool
    {
        $lock_table = $this->ensure_lock_table();
        $result = $this->db->table($lock_table)->where('lock_name', 'migration_process')->delete();
        if ($result === false) {
            log_message('warning', 'Failed to release migration lock');
        }
        return $result;
    }
    /**
     * Handles the actual running of a migration.
     *
     * @param string $direction "up" or "down"
     * @param object $migration The migration to run
     */
    protected function migrate($direction, $migration): bool
    {
        include_once $migration->path;
        $class = $migration->class;
        $this->set_name($migration->name);
        // Validate the migration file structure
        if (!class_exists($class, false)) {
            $message = sprintf(lang('Migrations.classNotFound'), $class);
            if ($this->silent) {
                $this->cli_messages[] = "\t" . CLI::color($message, 'red');
                return false;
            }
            throw new RuntimeException($message);
        }
        /** @var Migration $instance */
        $instance = new $class(Database::forge($this->db));
        $group = $instance->get_db_group() ?? $this->group;
        if (ENVIRONMENT !== 'testing' && $group === 'tests' && $this->group_filter !== 'tests') {
            // @codeCoverageIgnoreStart
            $this->group_skip = true;
            return true;
            // @codeCoverageIgnoreEnd
        }
        if ($direction === 'up' && $this->group_filter !== null && $this->group_filter !== $group) {
            $this->group_skip = true;
            return true;
        }
        if (!is_callable([$instance, $direction])) {
            $message = sprintf(lang('Migrations.missingMethod'), $direction);
            if ($this->silent) {
                $this->cli_messages[] = "\t" . CLI::color($message, 'red');
                return false;
            }
            throw new RuntimeException($message);
        }
        $instance->{$direction}();
        return true;
    }
}