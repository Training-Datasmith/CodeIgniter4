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

use Closure;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Exceptions\Data_Exception;
use Code_Igniter\Exceptions\InvalidArgumentException;
use Code_Igniter\Traits\Conditional_Trait;
use Config\Feature;
/**
 * Class BaseBuilder
 *
 * Provides the core Query Builder methods.
 * Database-specific Builders might need to override
 * certain methods to make them work.
 */
class Base_Builder
{
    use Conditional_Trait;
    /**
     * Reset DELETE data flag
     *
     * @var bool
     */
    protected $reset_delete_data = false;
    /**
     * QB SELECT data
     *
     * @var list<string>
     */
    protected $qb_select = [];
    /**
     * QB DISTINCT flag
     *
     * @var bool
     */
    protected $qb_distinct = false;
    /**
     * QB FROM data
     *
     * @var array
     */
    protected $qb_from = [];
    /**
     * QB JOIN data
     *
     * @var array
     */
    protected $qb_join = [];
    /**
     * QB WHERE data
     *
     * @var array
     */
    protected $qb_where = [];
    /**
     * QB GROUP BY data
     *
     * @var array
     */
    public $qb_group_by = [];
    /**
     * QB HAVING data
     *
     * @var array
     */
    protected $qb_having = [];
    /**
     * QB keys
     * list of column names.
     *
     * @var list<string>
     */
    protected $qb_keys = [];
    /**
     * QB LIMIT data
     *
     * @var bool|int
     */
    protected $qb_limit = false;
    /**
     * QB OFFSET data
     *
     * @var bool|int
     */
    protected $qb_offset = false;
    /**
     * QB ORDER BY data
     *
     * @var array|string|null
     */
    public $qb_order_by = [];
    /**
     * QB UNION data
     *
     * @var list<string>
     */
    protected array $qb_union = [];
    /**
     * Whether to protect identifiers in SELECT
     *
     * @var list<bool|null> true=protect, false=not protect
     */
    public $qb_no_escape = [];
    /**
     * QB data sets
     *
     * @var array<string, string>|list<list<int|string>>
     */
    protected $qb_set = [];
    /**
     * QB WHERE group started flag
     *
     * @var bool
     */
    protected $qb_where_group_started = false;
    /**
     * QB WHERE group count
     *
     * @var int
     */
    protected $qb_where_group_count = 0;
    /**
     * Ignore data that cause certain
     * exceptions, for example in case of
     * duplicate keys.
     *
     * @var bool
     */
    protected $qb_ignore = false;
    /**
     * QB Options data
     * Holds additional options and data used to render SQL
     * and is reset by resetWrite()
     *
     * @var array{
     *   updateFieldsAdditional?: array,
     *   tableIdentity?: string,
     *   updateFields?: array,
     *   constraints?: array,
     *   setQueryAsData?: string,
     *   sql?: string,
     *   alias?: string,
     *   fieldTypes?: array<string, array<string, string>>
     * }
     *
     * fieldTypes: [ProtectedTableName => [FieldName => Type]]
     */
    protected $qb_options;
    /**
     * A reference to the database connection.
     *
     * @var BaseConnection
     */
    protected $db;
    /**
     * Name of the primary table for this instance.
     * Tracked separately because $QBFrom gets escaped
     * and prefixed.
     *
     * When $tableName to the constructor has multiple tables,
     * the value is empty string.
     *
     * @var string
     */
    protected $table_name;
    /**
     * ORDER BY random keyword
     *
     * @var array
     */
    protected $random_keyword = ['RAND()', 'RAND(%d)'];
    /**
     * COUNT string
     *
     * @used-by CI_DB_driver::count_all()
     * @used-by BaseBuilder::count_all_results()
     *
     * @var string
     */
    protected $count_string = 'SELECT COUNT(*) AS ';
    /**
     * Collects the named parameters and
     * their values for later binding
     * in the Query object.
     *
     * @var array
     */
    protected $binds = [];
    /**
     * Collects the key count for named parameters
     * in the Query object.
     *
     * @var array
     */
    protected $binds_key_count = [];
    /**
     * Some databases, like SQLite, do not by default
     * allow limiting of delete clauses.
     *
     * @var bool
     */
    protected $can_limit_deletes = true;
    /**
     * Some databases do not by default
     * allow limit update queries with WHERE.
     *
     * @var bool
     */
    protected $can_limit_where_updates = true;
    /**
     * Specifies which sql statements
     * support the ignore option.
     *
     * @var array<string, string>
     */
    protected $supported_ignore_statements = [];
    /**
     * Builder testing mode status.
     *
     * @var bool
     */
    protected $test_mode = false;
    /**
     * Tables relation types
     *
     * @var array
     */
    protected $join_types = ['LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER'];
    /**
     * Strings that determine if a string represents a literal value or a field name
     *
     * @var list<string>
     */
    protected $is_literal_str = [];
    /**
     * RegExp used to get operators
     *
     * @var list<string>
     */
    protected $preg_operators = [];
    /**
     * Constructor
     *
     * @param array|string|TableName $tableName tablename or tablenames with or without aliases
     *
     * Examples of $tableName: `mytable`, `jobs j`, `jobs j, users u`, `['jobs j','users u']`
     *
     * @throws DatabaseException
     */
    public function __construct($table_name, Connection_Interface $db, ?array $options = null)
    {
        if (empty($table_name)) {
            throw new Database_Exception('A table must be specified when creating a new Query Builder.');
        }
        /**
         * @var BaseConnection $db
         */
        $this->db = $db;
        if ($table_name instanceof Table_Name) {
            $this->table_name = $table_name->get_table_name();
            $this->qb_from[] = $this->db->escape_identifier($table_name);
            $this->db->add_table_alias($table_name->get_alias());
        } elseif (is_string($table_name) && !str_contains($table_name, ',')) {
            $this->table_name = $table_name;
            // @TODO remove alias if exists
            $this->from($table_name);
        } else {
            $this->table_name = '';
            $this->from($table_name);
        }
        if ($options !== null && $options !== []) {
            foreach ($options as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $value;
                }
            }
        }
    }
    /**
     * Returns the current database connection
     *
     * @return BaseConnection
     */
    public function db(): Connection_Interface
    {
        return $this->db;
    }
    /**
     * Sets a test mode status.
     *
     * @return $this
     */
    public function test_mode(bool $mode = true)
    {
        $this->test_mode = $mode;
        return $this;
    }
    /**
     * Gets the name of the primary table.
     */
    public function get_table(): string
    {
        return $this->table_name;
    }
    /**
     * Returns an array of bind values and their
     * named parameters for binding in the Query object later.
     */
    public function get_binds(): array
    {
        return $this->binds;
    }
    /**
     * Ignore
     *
     * Set ignore Flag for next insert,
     * update or delete query.
     *
     * @return $this
     */
    public function ignore(bool $ignore = true)
    {
        $this->qb_ignore = $ignore;
        return $this;
    }
    /**
     * Generates the SELECT portion of the query
     *
     * @param list<RawSql|string>|RawSql|string $select
     * @param bool|null                         $escape Whether to protect identifiers
     *
     * @return $this
     */
    public function select($select = '*', ?bool $escape = null)
    {
        // If the escape value was not set, we will base it on the global setting
        if (!is_bool($escape)) {
            $escape = $this->db->protect_identifiers;
        }
        if ($select instanceof Raw_Sql) {
            $select = [$select];
        }
        if (is_string($select)) {
            $select = $escape === false ? [$select] : explode(',', $select);
        }
        foreach ($select as $val) {
            if ($val instanceof Raw_Sql) {
                $this->qb_select[] = $val;
                $this->qb_no_escape[] = false;
                continue;
            }
            $val = trim($val);
            if ($val !== '') {
                $this->qb_select[] = $val;
                /*
                 * When doing 'SELECT NULL as field_alias FROM table'
                 * null gets taken as a field, and therefore escaped
                 * with backticks.
                 * This prevents NULL being escaped
                 * @see https://github.com/codeigniter4/CodeIgniter4/issues/1169
                 */
                if (mb_stripos($val, 'NULL') === 0) {
                    $this->qb_no_escape[] = false;
                    continue;
                }
                $this->qb_no_escape[] = $escape;
            }
        }
        return $this;
    }
    /**
     * Generates a SELECT MAX(field) portion of a query
     *
     * @return $this
     */
    public function select_max(string $select = '', string $alias = '')
    {
        return $this->max_min_avg_sum($select, $alias);
    }
    /**
     * Generates a SELECT MIN(field) portion of a query
     *
     * @return $this
     */
    public function select_min(string $select = '', string $alias = '')
    {
        return $this->max_min_avg_sum($select, $alias, 'MIN');
    }
    /**
     * Generates a SELECT AVG(field) portion of a query
     *
     * @return $this
     */
    public function select_avg(string $select = '', string $alias = '')
    {
        return $this->max_min_avg_sum($select, $alias, 'AVG');
    }
    /**
     * Generates a SELECT SUM(field) portion of a query
     *
     * @return $this
     */
    public function select_sum(string $select = '', string $alias = '')
    {
        return $this->max_min_avg_sum($select, $alias, 'SUM');
    }
    /**
     * Generates a SELECT COUNT(field) portion of a query
     *
     * @return $this
     */
    public function select_count(string $select = '', string $alias = '')
    {
        return $this->max_min_avg_sum($select, $alias, 'COUNT');
    }
    /**
     * Adds a subquery to the selection
     */
    public function select_subquery(Base_Builder $subquery, string $as): self
    {
        $this->qb_select[] = $this->build_subquery($subquery, true, $as);
        return $this;
    }
    /**
     * SELECT [MAX|MIN|AVG|SUM|COUNT]()
     *
     * @used-by selectMax()
     * @used-by selectMin()
     * @used-by selectAvg()
     * @used-by selectSum()
     *
     * @return $this
     *
     * @throws DatabaseException
     * @throws DataException
     */
    protected function max_min_avg_sum(string $select = '', string $alias = '', string $type = 'MAX')
    {
        if ($select === '') {
            throw Data_Exception::for_empty_input_given('Select');
        }
        if (str_contains($select, ',')) {
            throw Data_Exception::for_invalid_argument('column name not separated by comma');
        }
        $type = strtoupper($type);
        if (!in_array($type, ['MAX', 'MIN', 'AVG', 'SUM', 'COUNT'], true)) {
            throw new Database_Exception('Invalid function type: ' . $type);
        }
        if ($alias === '') {
            $alias = $this->create_alias_from_table(trim($select));
        }
        $sql = $type . '(' . $this->db->protect_identifiers(trim($select)) . ') AS ' . $this->db->escape_identifiers(trim($alias));
        $this->qb_select[] = $sql;
        $this->qb_no_escape[] = null;
        return $this;
    }
    /**
     * Determines the alias name based on the table
     */
    protected function create_alias_from_table(string $item): string
    {
        if (str_contains($item, '.')) {
            $item = explode('.', $item);
            return end($item);
        }
        return $item;
    }
    /**
     * Sets a flag which tells the query string compiler to add DISTINCT
     *
     * @return $this
     */
    public function distinct(bool $val = true)
    {
        $this->qb_distinct = $val;
        return $this;
    }
    /**
     * Generates the FROM portion of the query
     *
     * @param array|string $from
     *
     * @return $this
     */
    public function from($from, bool $overwrite = false): self
    {
        if ($overwrite) {
            $this->qb_from = [];
            $this->db->set_aliased_tables([]);
        }
        foreach ((array) $from as $table) {
            if (str_contains($table, ',')) {
                $this->from(explode(',', $table));
            } else {
                $table = trim($table);
                if ($table === '') {
                    continue;
                }
                $this->track_aliases($table);
                $this->qb_from[] = $this->db->protect_identifiers($table, true, null, false);
            }
        }
        return $this;
    }
    /**
     * @param BaseBuilder $from  Expected subquery
     * @param string      $alias Subquery alias
     *
     * @return $this
     */
    public function from_subquery(Base_Builder $from, string $alias): self
    {
        $table = $this->build_subquery($from, true, $alias);
        $this->db->add_table_alias($alias);
        $this->qb_from[] = $table;
        return $this;
    }
    /**
     * Generates the JOIN portion of the query
     *
     * @param RawSql|string $cond
     *
     * @return $this
     */
    public function join(string $table, $cond, string $type = '', ?bool $escape = null)
    {
        if ($type !== '') {
            $type = strtoupper(trim($type));
            if (!in_array($type, $this->join_types, true)) {
                $type = '';
            } else {
                $type .= ' ';
            }
        }
        // Extract any aliases that might exist. We use this information
        // in the protectIdentifiers to know whether to add a table prefix
        $this->track_aliases($table);
        if (!is_bool($escape)) {
            $escape = $this->db->protect_identifiers;
        }
        // Do we want to escape the table name?
        if ($escape === true) {
            $table = $this->db->protect_identifiers($table, true, null, false);
        }
        if ($cond instanceof Raw_Sql) {
            $this->qb_join[] = $type . 'JOIN ' . $table . ' ON ' . $cond;
            return $this;
        }
        if (!$this->has_operator($cond)) {
            $cond = ' USING (' . ($escape ? $this->db->escape_identifiers($cond) : $cond) . ')';
        } elseif ($escape === false) {
            $cond = ' ON ' . $cond;
        } else {
            // Split multiple conditions
            // @TODO This does not parse `BETWEEN a AND b` correctly.
            if (preg_match_all('/\sAND\s|\sOR\s/i', $cond, $joints, PREG_OFFSET_CAPTURE) >= 1) {
                $conditions = [];
                $joints = $joints[0];
                array_unshift($joints, ['', 0]);
                for ($i = count($joints) - 1, $pos = strlen($cond); $i >= 0; $i--) {
                    $joints[$i][1] += strlen($joints[$i][0]);
                    // offset
                    $conditions[$i] = substr($cond, $joints[$i][1], $pos - $joints[$i][1]);
                    $pos = $joints[$i][1] - strlen($joints[$i][0]);
                    $joints[$i] = $joints[$i][0];
                }
                ksort($conditions);
            } else {
                $conditions = [$cond];
                $joints = [''];
            }
            $cond = ' ON ';
            foreach ($conditions as $i => $condition) {
                $operator = $this->get_operator($condition);
                // Workaround for BETWEEN
                if ($operator === false) {
                    $cond .= $joints[$i] . $condition;
                    continue;
                }
                $cond .= $joints[$i];
                $cond .= preg_match('/(\(*)?([\[\]\w\.\'-]+)' . preg_quote($operator, '/') . '(.*)/i', $condition, $match) ? $match[1] . $this->db->protect_identifiers($match[2]) . $operator . $this->db->protect_identifiers($match[3]) : $condition;
            }
        }
        // Assemble the JOIN statement
        $this->qb_join[] = $type . 'JOIN ' . $table . $cond;
        return $this;
    }
    /**
     * Generates the WHERE portion of the query.
     * Separates multiple calls with 'AND'.
     *
     * @param array|RawSql|string $key
     * @param mixed               $value
     *
     * @return $this
     */
    public function where($key, $value = null, ?bool $escape = null)
    {
        return $this->where_having('QBWhere', $key, $value, 'AND ', $escape);
    }
    /**
     * OR WHERE
     *
     * Generates the WHERE portion of the query.
     * Separates multiple calls with 'OR'.
     *
     * @param array|RawSql|string $key
     * @param mixed               $value
     *
     * @return $this
     */
    public function or_where($key, $value = null, ?bool $escape = null)
    {
        return $this->where_having('QBWhere', $key, $value, 'OR ', $escape);
    }
    /**
     * @used-by where()
     * @used-by orWhere()
     * @used-by having()
     * @used-by orHaving()
     *
     * @param array|RawSql|string $key
     * @param mixed               $value
     *
     * @return $this
     */
    protected function where_having(string $qb_key, $key, $value = null, string $type = 'AND ', ?bool $escape = null)
    {
        $raw_sql_only = false;
        if ($key instanceof Raw_Sql) {
            if ($value === null) {
                $key_value = [(string) $key => $key];
                $raw_sql_only = true;
            } else {
                $key_value = [(string) $key => $value];
            }
        } elseif (!is_array($key)) {
            $key_value = [$key => $value];
        } else {
            $key_value = $key;
        }
        // If the escape value was not set will base it on the global setting
        if (!is_bool($escape)) {
            $escape = $this->db->protect_identifiers;
        }
        foreach ($key_value as $k => $v) {
            $prefix = empty($this->{$qb_key}) ? $this->group_get_type('') : $this->group_get_type($type);
            if ($raw_sql_only) {
                $k = '';
                $op = '';
            } elseif ($v !== null) {
                $op = $this->get_operator_from_where_key($k);
                if (!empty($op)) {
                    $k = trim($k);
                    end($op);
                    $op = trim(current($op));
                    // Does the key end with operator?
                    if (str_ends_with($k, $op)) {
                        $k = rtrim(substr($k, 0, -strlen($op)));
                        $op = " {$op}";
                    } else {
                        $op = '';
                    }
                } else {
                    $op = ' =';
                }
                if ($this->is_subquery($v)) {
                    $v = $this->build_subquery($v, true);
                } else {
                    $bind = $this->set_bind($k, $v, $escape);
                    $v = " :{$bind}:";
                }
            } elseif (!$this->has_operator($k) && $qb_key !== 'QBHaving') {
                // value appears not to have been set, assign the test to IS NULL
                $op = ' IS NULL';
            } elseif (preg_match('/\s*(!?=|<>|IS(?:\s+NOT)?)\s*$/i', $k, $match, PREG_OFFSET_CAPTURE)) {
                $k = substr($k, 0, $match[0][1]);
                $op = $match[1][0] === '=' ? ' IS NULL' : ' IS NOT NULL';
            } else {
                $op = '';
            }
            if ($v instanceof Raw_Sql) {
                $this->{$qb_key}[] = ['condition' => $v->with($prefix . $k . $op . $v), 'escape' => $escape];
            } else {
                $this->{$qb_key}[] = ['condition' => $prefix . $k . $op . $v, 'escape' => $escape];
            }
        }
        return $this;
    }
    /**
     * Generates a WHERE field IN('item', 'item') SQL query,
     * joined with 'AND' if appropriate.
     *
     * @param array|BaseBuilder|(Closure(BaseBuilder): BaseBuilder)|null $values The values searched on, or anonymous function with subquery
     *
     * @return $this
     */
    public function where_in(?string $key = null, $values = null, ?bool $escape = null)
    {
        return $this->_where_in($key, $values, false, 'AND ', $escape);
    }
    /**
     * Generates a WHERE field IN('item', 'item') SQL query,
     * joined with 'OR' if appropriate.
     *
     * @param array|BaseBuilder|(Closure(BaseBuilder): BaseBuilder)|null $values The values searched on, or anonymous function with subquery
     *
     * @return $this
     */
    public function or_where_in(?string $key = null, $values = null, ?bool $escape = null)
    {
        return $this->_where_in($key, $values, false, 'OR ', $escape);
    }
    /**
     * Generates a WHERE field NOT IN('item', 'item') SQL query,
     * joined with 'AND' if appropriate.
     *
     * @param array|BaseBuilder|(Closure(BaseBuilder): BaseBuilder)|null $values The values searched on, or anonymous function with subquery
     *
     * @return $this
     */
    public function where_not_in(?string $key = null, $values = null, ?bool $escape = null)
    {
        return $this->_where_in($key, $values, true, 'AND ', $escape);
    }
    /**
     * Generates a WHERE field NOT IN('item', 'item') SQL query,
     * joined with 'OR' if appropriate.
     *
     * @param array|BaseBuilder|(Closure(BaseBuilder): BaseBuilder)|null $values The values searched on, or anonymous function with subquery
     *
     * @return $this
     */
    public function or_where_not_in(?string $key = null, $values = null, ?bool $escape = null)
    {
        return $this->_where_in($key, $values, true, 'OR ', $escape);
    }
    /**
     * Generates a HAVING field IN('item', 'item') SQL query,
     * joined with 'AND' if appropriate.
     *
     * @param array|BaseBuilder|(Closure(BaseBuilder): BaseBuilder)|null $values The values searched on, or anonymous function with subquery
     *
     * @return $this
     */
    public function having_in(?string $key = null, $values = null, ?bool $escape = null)
    {
        return $this->_where_in($key, $values, false, 'AND ', $escape, 'QBHaving');
    }
    /**
     * Generates a HAVING field IN('item', 'item') SQL query,
     * joined with 'OR' if appropriate.
     *
     * @param array|BaseBuilder|(Closure(BaseBuilder): BaseBuilder)|null $values The values searched on, or anonymous function with subquery
     *
     * @return $this
     */
    public function or_having_in(?string $key = null, $values = null, ?bool $escape = null)
    {
        return $this->_where_in($key, $values, false, 'OR ', $escape, 'QBHaving');
    }
    /**
     * Generates a HAVING field NOT IN('item', 'item') SQL query,
     * joined with 'AND' if appropriate.
     *
     * @param array|BaseBuilder|(Closure(BaseBuilder):BaseBuilder)|null $values The values searched on, or anonymous function with subquery
     *
     * @return $this
     */
    public function having_not_in(?string $key = null, $values = null, ?bool $escape = null)
    {
        return $this->_where_in($key, $values, true, 'AND ', $escape, 'QBHaving');
    }
    /**
     * Generates a HAVING field NOT IN('item', 'item') SQL query,
     * joined with 'OR' if appropriate.
     *
     * @param array|BaseBuilder|(Closure(BaseBuilder): BaseBuilder)|null $values The values searched on, or anonymous function with subquery
     *
     * @return $this
     */
    public function or_having_not_in(?string $key = null, $values = null, ?bool $escape = null)
    {
        return $this->_where_in($key, $values, true, 'OR ', $escape, 'QBHaving');
    }
    /**
     * @used-by WhereIn()
     * @used-by orWhereIn()
     * @used-by whereNotIn()
     * @used-by orWhereNotIn()
     *
     * @param non-empty-string|null                                            $key
     * @param BaseBuilder|(Closure(BaseBuilder): BaseBuilder)|list<mixed>|null $values The values searched on, or anonymous function with subquery
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    protected function _where_in(?string $key = null, $values = null, bool $not = false, string $type = 'AND ', ?bool $escape = null, string $clause = 'QBWhere')
    {
        if ($key === null || $key === '') {
            throw new InvalidArgumentException(sprintf('%s() expects $key to be a non-empty string', debug_backtrace(0, 2)[1]['function']));
        }
        if ($values === null || !is_array($values) && !$this->is_subquery($values)) {
            throw new InvalidArgumentException(sprintf('%s() expects $values to be of type array or closure', debug_backtrace(0, 2)[1]['function']));
        }
        if (!is_bool($escape)) {
            $escape = $this->db->protect_identifiers;
        }
        $ok = $key;
        if ($escape === true) {
            $key = $this->db->protect_identifiers($key);
        }
        $not = $not ? ' NOT' : '';
        if ($this->is_subquery($values)) {
            $where_in = $this->build_subquery($values, true);
            $escape = false;
        } else {
            $where_in = array_values($values);
        }
        $ok = $this->set_bind($ok, $where_in, $escape);
        $prefix = empty($this->{$clause}) ? $this->group_get_type('') : $this->group_get_type($type);
        $where_in = ['condition' => "{$prefix}{$key}{$not} IN :{$ok}:", 'escape' => false];
        $this->{$clause}[] = $where_in;
        return $this;
    }
    /**
     * Generates a %LIKE% portion of the query.
     * Separates multiple calls with 'AND'.
     *
     * @param array|RawSql|string $field
     *
     * @return $this
     */
    public function like($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitive_search = false)
    {
        return $this->_like($field, $match, 'AND ', $side, '', $escape, $insensitive_search);
    }
    /**
     * Generates a NOT LIKE portion of the query.
     * Separates multiple calls with 'AND'.
     *
     * @param array|RawSql|string $field
     *
     * @return $this
     */
    public function not_like($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitive_search = false)
    {
        return $this->_like($field, $match, 'AND ', $side, 'NOT', $escape, $insensitive_search);
    }
    /**
     * Generates a %LIKE% portion of the query.
     * Separates multiple calls with 'OR'.
     *
     * @param array|RawSql|string $field
     *
     * @return $this
     */
    public function or_like($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitive_search = false)
    {
        return $this->_like($field, $match, 'OR ', $side, '', $escape, $insensitive_search);
    }
    /**
     * Generates a NOT LIKE portion of the query.
     * Separates multiple calls with 'OR'.
     *
     * @param array|RawSql|string $field
     *
     * @return $this
     */
    public function or_not_like($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitive_search = false)
    {
        return $this->_like($field, $match, 'OR ', $side, 'NOT', $escape, $insensitive_search);
    }
    /**
     * Generates a %LIKE% portion of the query.
     * Separates multiple calls with 'AND'.
     *
     * @param array|RawSql|string $field
     *
     * @return $this
     */
    public function having_like($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitive_search = false)
    {
        return $this->_like($field, $match, 'AND ', $side, '', $escape, $insensitive_search, 'QBHaving');
    }
    /**
     * Generates a NOT LIKE portion of the query.
     * Separates multiple calls with 'AND'.
     *
     * @param array|RawSql|string $field
     *
     * @return $this
     */
    public function not_having_like($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitive_search = false)
    {
        return $this->_like($field, $match, 'AND ', $side, 'NOT', $escape, $insensitive_search, 'QBHaving');
    }
    /**
     * Generates a %LIKE% portion of the query.
     * Separates multiple calls with 'OR'.
     *
     * @param array|RawSql|string $field
     *
     * @return $this
     */
    public function or_having_like($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitive_search = false)
    {
        return $this->_like($field, $match, 'OR ', $side, '', $escape, $insensitive_search, 'QBHaving');
    }
    /**
     * Generates a NOT LIKE portion of the query.
     * Separates multiple calls with 'OR'.
     *
     * @param array|RawSql|string $field
     *
     * @return $this
     */
    public function or_not_having_like($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitive_search = false)
    {
        return $this->_like($field, $match, 'OR ', $side, 'NOT', $escape, $insensitive_search, 'QBHaving');
    }
    /**
     * @used-by like()
     * @used-by orLike()
     * @used-by notLike()
     * @used-by orNotLike()
     * @used-by havingLike()
     * @used-by orHavingLike()
     * @used-by notHavingLike()
     * @used-by orNotHavingLike()
     *
     * @param array<string, string>|RawSql|string $field
     *
     * @return $this
     */
    protected function _like($field, string $match = '', string $type = 'AND ', string $side = 'both', string $not = '', ?bool $escape = null, bool $insensitive_search = false, string $clause = 'QBWhere')
    {
        $escape = is_bool($escape) ? $escape : $this->db->protect_identifiers;
        $side = strtolower($side);
        if ($field instanceof Raw_Sql) {
            $k = (string) $field;
            $v = $match;
            $insensitive_search = false;
            $prefix = empty($this->{$clause}) ? $this->group_get_type('') : $this->group_get_type($type);
            if ($side === 'none') {
                $bind = $this->set_bind($field->get_binding_key(), $v, $escape);
            } elseif ($side === 'before') {
                $bind = $this->set_bind($field->get_binding_key(), "%{$v}", $escape);
            } elseif ($side === 'after') {
                $bind = $this->set_bind($field->get_binding_key(), "{$v}%", $escape);
            } else {
                $bind = $this->set_bind($field->get_binding_key(), "%{$v}%", $escape);
            }
            $like_statement = $this->_like_statement($prefix, $k, $not, $bind, $insensitive_search);
            // some platforms require an escape sequence definition for LIKE wildcards
            if ($escape === true && $this->db->like_escape_str !== '') {
                $like_statement .= sprintf($this->db->like_escape_str, $this->db->like_escape_char);
            }
            $this->{$clause}[] = ['condition' => $field->with($like_statement), 'escape' => $escape];
            return $this;
        }
        $key_value = is_array($field) ? $field : [$field => $match];
        foreach ($key_value as $k => $v) {
            if ($insensitive_search) {
                $v = mb_strtolower($v, 'UTF-8');
            }
            $prefix = empty($this->{$clause}) ? $this->group_get_type('') : $this->group_get_type($type);
            if ($side === 'none') {
                $bind = $this->set_bind($k, $v, $escape);
            } elseif ($side === 'before') {
                $bind = $this->set_bind($k, "%{$v}", $escape);
            } elseif ($side === 'after') {
                $bind = $this->set_bind($k, "{$v}%", $escape);
            } else {
                $bind = $this->set_bind($k, "%{$v}%", $escape);
            }
            $like_statement = $this->_like_statement($prefix, $k, $not, $bind, $insensitive_search);
            // some platforms require an escape sequence definition for LIKE wildcards
            if ($escape === true && $this->db->like_escape_str !== '') {
                $like_statement .= sprintf($this->db->like_escape_str, $this->db->like_escape_char);
            }
            $this->{$clause}[] = ['condition' => $like_statement, 'escape' => $escape];
        }
        return $this;
    }
    /**
     * Platform independent LIKE statement builder.
     */
    protected function _like_statement(?string $prefix, string $column, ?string $not, string $bind, bool $insensitive_search = false): string
    {
        if ($insensitive_search) {
            return "{$prefix} LOWER(" . $this->db->escape_identifiers($column) . ") {$not} LIKE :{$bind}:";
        }
        return "{$prefix} {$column} {$not} LIKE :{$bind}:";
    }
    /**
     * Add UNION statement
     *
     * @param BaseBuilder|Closure(BaseBuilder): BaseBuilder $union
     *
     * @return $this
     */
    public function union($union)
    {
        return $this->add_union_statement($union);
    }
    /**
     * Add UNION ALL statement
     *
     * @param BaseBuilder|Closure(BaseBuilder): BaseBuilder $union
     *
     * @return $this
     */
    public function union_all($union)
    {
        return $this->add_union_statement($union, true);
    }
    /**
     * @used-by union()
     * @used-by unionAll()
     *
     * @param BaseBuilder|Closure(BaseBuilder): BaseBuilder $union
     *
     * @return $this
     */
    protected function add_union_statement($union, bool $all = false)
    {
        $this->qb_union[] = "\nUNION " . ($all ? 'ALL ' : '') . 'SELECT * FROM ' . $this->build_subquery($union, true, 'uwrp' . (count($this->qb_union) + 1));
        return $this;
    }
    /**
     * Starts a query group.
     *
     * @return $this
     */
    public function group_start()
    {
        return $this->group_start_prepare();
    }
    /**
     * Starts a query group, but ORs the group
     *
     * @return $this
     */
    public function or_group_start()
    {
        return $this->group_start_prepare('', 'OR ');
    }
    /**
     * Starts a query group, but NOTs the group
     *
     * @return $this
     */
    public function not_group_start()
    {
        return $this->group_start_prepare('NOT ');
    }
    /**
     * Starts a query group, but OR NOTs the group
     *
     * @return $this
     */
    public function or_not_group_start()
    {
        return $this->group_start_prepare('NOT ', 'OR ');
    }
    /**
     * Ends a query group
     *
     * @return $this
     */
    public function group_end()
    {
        return $this->group_end_prepare();
    }
    /**
     * Starts a query group for HAVING clause.
     *
     * @return $this
     */
    public function having_group_start()
    {
        return $this->group_start_prepare('', 'AND ', 'QBHaving');
    }
    /**
     * Starts a query group for HAVING clause, but ORs the group.
     *
     * @return $this
     */
    public function or_having_group_start()
    {
        return $this->group_start_prepare('', 'OR ', 'QBHaving');
    }
    /**
     * Starts a query group for HAVING clause, but NOTs the group.
     *
     * @return $this
     */
    public function not_having_group_start()
    {
        return $this->group_start_prepare('NOT ', 'AND ', 'QBHaving');
    }
    /**
     * Starts a query group for HAVING clause, but OR NOTs the group.
     *
     * @return $this
     */
    public function or_not_having_group_start()
    {
        return $this->group_start_prepare('NOT ', 'OR ', 'QBHaving');
    }
    /**
     * Ends a query group for HAVING clause.
     *
     * @return $this
     */
    public function having_group_end()
    {
        return $this->group_end_prepare('QBHaving');
    }
    /**
     * Prepate a query group start.
     *
     * @return $this
     */
    protected function group_start_prepare(string $not = '', string $type = 'AND ', string $clause = 'QBWhere')
    {
        $type = $this->group_get_type($type);
        $this->qb_where_group_started = true;
        $prefix = empty($this->{$clause}) ? '' : $type;
        $where = ['condition' => $prefix . $not . str_repeat(' ', ++$this->qb_where_group_count) . ' (', 'escape' => false];
        $this->{$clause}[] = $where;
        return $this;
    }
    /**
     * Prepate a query group end.
     *
     * @return $this
     */
    protected function group_end_prepare(string $clause = 'QBWhere')
    {
        $this->qb_where_group_started = false;
        $where = ['condition' => str_repeat(' ', $this->qb_where_group_count--) . ')', 'escape' => false];
        $this->{$clause}[] = $where;
        return $this;
    }
    /**
     * @used-by groupStart()
     * @used-by _like()
     * @used-by whereHaving()
     * @used-by _whereIn()
     * @used-by havingGroupStart()
     */
    protected function group_get_type(string $type): string
    {
        if ($this->qb_where_group_started) {
            $type = '';
            $this->qb_where_group_started = false;
        }
        return $type;
    }
    /**
     * @param array|string $by
     *
     * @return $this
     */
    public function group_by($by, ?bool $escape = null)
    {
        if (!is_bool($escape)) {
            $escape = $this->db->protect_identifiers;
        }
        if (is_string($by)) {
            $by = $escape === true ? explode(',', $by) : [$by];
        }
        foreach ($by as $val) {
            $val = trim($val);
            if ($val !== '') {
                $val = ['field' => $val, 'escape' => $escape];
                $this->qb_group_by[] = $val;
            }
        }
        return $this;
    }
    /**
     * Separates multiple calls with 'AND'.
     *
     * @param array|RawSql|string $key
     * @param mixed               $value
     *
     * @return $this
     */
    public function having($key, $value = null, ?bool $escape = null)
    {
        return $this->where_having('QBHaving', $key, $value, 'AND ', $escape);
    }
    /**
     * Separates multiple calls with 'OR'.
     *
     * @param array|RawSql|string $key
     * @param mixed               $value
     *
     * @return $this
     */
    public function or_having($key, $value = null, ?bool $escape = null)
    {
        return $this->where_having('QBHaving', $key, $value, 'OR ', $escape);
    }
    /**
     * @param string $direction ASC, DESC or RANDOM
     *
     * @return $this
     */
    public function order_by(string $order_by, string $direction = '', ?bool $escape = null)
    {
        if ($order_by === '') {
            return $this;
        }
        $qb_order_by = [];
        $direction = strtoupper(trim($direction));
        if ($direction === 'RANDOM') {
            $direction = '';
            $order_by = ctype_digit($order_by) ? sprintf($this->random_keyword[1], $order_by) : $this->random_keyword[0];
            $escape = false;
        } elseif ($direction !== '') {
            $direction = in_array($direction, ['ASC', 'DESC'], true) ? ' ' . $direction : '';
        }
        if ($escape === null) {
            $escape = $this->db->protect_identifiers;
        }
        if ($escape === false) {
            $qb_order_by[] = ['field' => $order_by, 'direction' => $direction, 'escape' => false];
        } else {
            foreach (explode(',', $order_by) as $field) {
                $qb_order_by[] = $direction === '' && preg_match('/\s+(ASC|DESC)$/i', rtrim($field), $match, PREG_OFFSET_CAPTURE) ? ['field' => ltrim(substr($field, 0, $match[0][1])), 'direction' => ' ' . $match[1][0], 'escape' => true] : ['field' => trim($field), 'direction' => $direction, 'escape' => true];
            }
        }
        $this->qb_order_by = array_merge($this->qb_order_by, $qb_order_by);
        return $this;
    }
    /**
     * @return $this
     */
    public function limit(?int $value = null, ?int $offset = 0)
    {
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if ($limit_zero_as_all && $value === 0) {
            $value = null;
        }
        if ($value !== null) {
            $this->qb_limit = $value;
        }
        if ($offset !== null && $offset !== 0) {
            $this->qb_offset = $offset;
        }
        return $this;
    }
    /**
     * Sets the OFFSET value
     *
     * @return $this
     */
    public function offset(int $offset)
    {
        if ($offset !== 0) {
            $this->qb_offset = $offset;
        }
        return $this;
    }
    /**
     * Generates a platform-specific LIMIT clause.
     */
    protected function _limit(string $sql, bool $offset_ignore = false): string
    {
        return $sql . ' LIMIT ' . ($offset_ignore === false && $this->qb_offset ? $this->qb_offset . ', ' : '') . $this->qb_limit;
    }
    /**
     * Allows key/value pairs to be set for insert(), update() or replace().
     *
     * @param array|object|string $key    Field name, or an array of field/value pairs, or an object
     * @param mixed               $value  Field value, if $key is a single field
     * @param bool|null           $escape Whether to escape values
     *
     * @return $this
     */
    public function set($key, $value = '', ?bool $escape = null)
    {
        $key = $this->object_to_array($key);
        if (!is_array($key)) {
            $key = [$key => $value];
        }
        $escape = is_bool($escape) ? $escape : $this->db->protect_identifiers;
        foreach ($key as $k => $v) {
            if ($escape) {
                $bind = $this->set_bind($k, $v, $escape);
                $this->qb_set[$this->db->protect_identifiers($k, false)] = ":{$bind}:";
            } else {
                $this->qb_set[$this->db->protect_identifiers($k, false)] = $v;
            }
        }
        return $this;
    }
    /**
     * Returns the previously set() data, alternatively resetting it if needed.
     */
    public function get_set_data(bool $clean = false): array
    {
        $data = $this->qb_set;
        if ($clean) {
            $this->qb_set = [];
        }
        return $data;
    }
    /**
     * Compiles a SELECT query string and returns the sql.
     */
    public function get_compiled_select(bool $reset = true): string
    {
        $select = $this->compile_select();
        if ($reset) {
            $this->reset_select();
        }
        return $this->compile_final_query($select);
    }
    /**
     * Returns a finalized, compiled query string with the bindings
     * inserted and prefixes swapped out.
     */
    protected function compile_final_query(string $sql): string
    {
        $query = new Query($this->db);
        $query->set_query($sql, $this->binds, false);
        if (!empty($this->db->swap_pre) && !empty($this->db->db_prefix)) {
            $query->swap_prefix($this->db->db_prefix, $this->db->swap_pre);
        }
        return $query->get_query();
    }
    /**
     * Compiles the select statement based on the other functions called
     * and runs the query
     *
     * @return false|ResultInterface
     */
    public function get(?int $limit = null, int $offset = 0, bool $reset = true)
    {
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if ($limit_zero_as_all && $limit === 0) {
            $limit = null;
        }
        if ($limit !== null) {
            $this->limit($limit, $offset);
        }
        $result = $this->test_mode ? $this->get_compiled_select($reset) : $this->db->query($this->compile_select(), $this->binds, false);
        if ($reset) {
            $this->reset_select();
            // Clear our binds so we don't eat up memory
            $this->binds = [];
        }
        return $result;
    }
    /**
     * Generates a platform-specific query string that counts all records in
     * the particular table
     *
     * @return int|string
     */
    public function count_all(bool $reset = true)
    {
        $table = $this->qb_from[0];
        $sql = $this->count_string . $this->db->escape_identifiers('numrows') . ' FROM ' . $this->db->protect_identifiers($table, true, null, false);
        if ($this->test_mode) {
            return $sql;
        }
        $query = $this->db->query($sql, null, false);
        if (empty($query->get_result())) {
            return 0;
        }
        $query = $query->get_row();
        if ($reset) {
            $this->reset_select();
        }
        return (int) $query->numrows;
    }
    /**
     * Generates a platform-specific query string that counts all records
     * returned by an Query Builder query.
     *
     * @return int|string
     */
    public function count_all_results(bool $reset = true)
    {
        // ORDER BY usage is often problematic here (most notably
        // on Microsoft SQL Server) and ultimately unnecessary
        // for selecting COUNT(*) ...
        $order_by = [];
        if (!empty($this->qb_order_by)) {
            $order_by = $this->qb_order_by;
            $this->qb_order_by = null;
        }
        // We cannot use a LIMIT when getting the single row COUNT(*) result
        $limit = $this->qb_limit;
        $this->qb_limit = false;
        if ($this->qb_distinct === true || !empty($this->qb_group_by)) {
            // We need to backup the original SELECT in case DBPrefix is used
            $select = $this->qb_select;
            $sql = $this->count_string . $this->db->protect_identifiers('numrows') . "\nFROM (\n" . $this->compile_select() . "\n) CI_count_all_results";
            // Restore SELECT part
            $this->qb_select = $select;
            unset($select);
        } else {
            $sql = $this->compile_select($this->count_string . $this->db->protect_identifiers('numrows'));
        }
        if ($this->test_mode) {
            return $sql;
        }
        $result = $this->db->query($sql, $this->binds, false);
        if ($reset) {
            $this->reset_select();
        } elseif (!isset($this->qb_order_by)) {
            $this->qb_order_by = $order_by;
        }
        // Restore the LIMIT setting
        $this->qb_limit = $limit;
        $row = $result instanceof Result_Interface ? $result->get_row() : null;
        if (empty($row)) {
            return 0;
        }
        return (int) $row->numrows;
    }
    /**
     * Compiles the set conditions and returns the sql statement
     *
     * @return array
     */
    public function get_compiled_qb_where()
    {
        return $this->qb_where;
    }
    /**
     * Allows the where clause, limit and offset to be added directly
     *
     * @param array|string $where
     *
     * @return ResultInterface
     */
    public function get_where($where = null, ?int $limit = null, ?int $offset = 0, bool $reset = true)
    {
        if ($where !== null) {
            $this->where($where);
        }
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if ($limit_zero_as_all && $limit === 0) {
            $limit = null;
        }
        if ($limit !== null) {
            $this->limit($limit, $offset);
        }
        $result = $this->test_mode ? $this->get_compiled_select($reset) : $this->db->query($this->compile_select(), $this->binds, false);
        if ($reset) {
            $this->reset_select();
            // Clear our binds so we don't eat up memory
            $this->binds = [];
        }
        return $result;
    }
    /**
     * Compiles batch insert/update/upsert strings and runs the queries
     *
     * @param '_deleteBatch'|'_insertBatch'|'_updateBatch'|'_upsertBatch' $renderMethod
     *
     * @return false|int|list<string> Number of rows inserted or FALSE on failure, SQL array when testMode
     *
     * @throws DatabaseException
     */
    protected function batch_execute(string $render_method, int $batch_size = 100)
    {
        if (empty($this->qb_set)) {
            if ($this->db->db_debug) {
                throw new Database_Exception(trim($render_method, '_') . '() has no data.');
            }
            return false;
            // @codeCoverageIgnore
        }
        $table = $this->db->protect_identifiers($this->qb_from[0], true, null, false);
        $affected_rows = 0;
        $saved_sql = [];
        $cnt = count($this->qb_set);
        // batch size 0 for unlimited
        if ($batch_size === 0) {
            $batch_size = $cnt;
        }
        for ($i = 0, $total = $cnt; $i < $total; $i += $batch_size) {
            $qb_set = array_slice($this->qb_set, $i, $batch_size);
            $sql = $this->{$render_method}($table, $this->qb_keys, $qb_set);
            if ($sql === '') {
                return false;
                // @codeCoverageIgnore
            }
            if ($this->test_mode) {
                $saved_sql[] = $sql;
            } else {
                $this->db->query($sql, null, false);
                $affected_rows += $this->db->affected_rows();
            }
        }
        if (!$this->test_mode) {
            $this->reset_write();
        }
        return $this->test_mode ? $saved_sql : $affected_rows;
    }
    /**
     * Allows a row or multiple rows to be set for batch inserts/upserts/updates
     *
     * @param array|object $set
     * @param string       $alias alias for sql table
     *
     * @return $this|null
     */
    public function set_data($set, ?bool $escape = null, string $alias = '')
    {
        if (empty($set)) {
            if ($this->db->db_debug) {
                throw new Database_Exception('setData() has no data.');
            }
            return null;
            // @codeCoverageIgnore
        }
        $this->set_alias($alias);
        // this allows to set just one row at a time
        if (is_object($set) || !is_array(current($set)) && !is_object(current($set))) {
            $set = [$set];
        }
        $set = $this->batch_object_to_array($set);
        $escape = is_bool($escape) ? $escape : $this->db->protect_identifiers;
        $keys = array_keys($this->object_to_array(current($set)));
        sort($keys);
        foreach ($set as $row) {
            $row = $this->object_to_array($row);
            if (array_diff($keys, array_keys($row)) !== [] || array_diff(array_keys($row), $keys) !== []) {
                // batchExecute() function returns an error on an empty array
                $this->qb_set[] = [];
                return null;
            }
            ksort($row);
            // puts $row in the same order as our keys
            $clean = [];
            foreach ($row as $row_value) {
                $clean[] = $escape ? $this->db->escape($row_value) : $row_value;
            }
            $row = $clean;
            $this->qb_set[] = $row;
        }
        foreach ($keys as $k) {
            $k = $this->db->protect_identifiers($k, false);
            if (!in_array($k, $this->qb_keys, true)) {
                $this->qb_keys[] = $k;
            }
        }
        return $this;
    }
    /**
     * Compiles an upsert query and returns the sql
     *
     * @return string
     *
     * @throws DatabaseException
     */
    public function get_compiled_upsert()
    {
        [$current_test_mode, $this->test_mode] = [$this->test_mode, true];
        $sql = implode(";\n", $this->upsert());
        $this->test_mode = $current_test_mode;
        return $this->compile_final_query($sql);
    }
    /**
     * Converts call to batchUpsert
     *
     * @param array|object|null $set
     *
     * @return false|int|list<string> Number of affected rows or FALSE on failure, SQL array when testMode
     *
     * @throws DatabaseException
     */
    public function upsert($set = null, ?bool $escape = null)
    {
        // if set() has been used merge QBSet with binds and then setData()
        if ($set === null && !is_array(current($this->qb_set))) {
            $set = [];
            foreach ($this->qb_set as $field => $value) {
                $k = trim($field, $this->db->escape_char);
                // use binds if available else use QBSet value but with RawSql to avoid escape
                $set[$k] = isset($this->binds[$k]) ? $this->binds[$k][0] : new Raw_Sql($value);
            }
            $this->binds = [];
            $this->reset_run(['QBSet' => [], 'QBKeys' => []]);
            $this->set_data($set, true);
            // unescaped items are RawSql now
        } elseif ($set !== null) {
            $this->set_data($set, $escape);
        }
        // else setData() has already been used and we need to do nothing
        return $this->batch_execute('_upsertBatch');
    }
    /**
     * Compiles batch upsert strings and runs the queries
     *
     * @param array|object|null $set a dataset
     *
     * @return false|int|list<string> Number of affected rows or FALSE on failure, SQL array when testMode
     *
     * @throws DatabaseException
     */
    public function upsert_batch($set = null, ?bool $escape = null, int $batch_size = 100)
    {
        if (isset($this->qb_options['setQueryAsData'])) {
            $sql = $this->_upsert_batch($this->qb_from[0], $this->qb_keys, []);
            if ($sql === '') {
                return false;
                // @codeCoverageIgnore
            }
            if ($this->test_mode === false) {
                $this->db->query($sql, null, false);
            }
            $this->reset_write();
            return $this->test_mode ? $sql : $this->db->affected_rows();
        }
        if ($set !== null) {
            $this->set_data($set, $escape);
        }
        return $this->batch_execute('_upsertBatch', $batch_size);
    }
    /**
     * Generates a platform-specific upsertBatch string from the supplied data
     *
     * @used-by batchExecute()
     *
     * @param string                 $table  Protected table name
     * @param list<string>           $keys   QBKeys
     * @param list<list<int|string>> $values QBSet
     */
    protected function _upsert_batch(string $table, array $keys, array $values): string
    {
        $sql = $this->qb_options['sql'] ?? '';
        // if this is the first iteration of batch then we need to build skeleton sql
        if ($sql === '') {
            $update_fields = $this->qb_options['updateFields'] ?? $this->update_fields($keys)->qb_options['updateFields'] ?? [];
            $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $keys) . ")\n{:_table_:}ON DUPLICATE KEY UPDATE\n" . implode(",\n", array_map(static fn($key, $value): string => $table . '.' . $key . ($value instanceof Raw_Sql ? ' = ' . $value : ' = VALUES(' . $value . ')'), array_keys($update_fields), $update_fields));
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'] . "\n";
        } else {
            $data = 'VALUES ' . implode(', ', $this->format_values($values)) . "\n";
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
    /**
     * Set table alias for dataset pseudo table.
     */
    private function set_alias(string $alias): Base_Builder
    {
        if ($alias !== '') {
            $this->db->add_table_alias($alias);
            $this->qb_options['alias'] = $this->db->protect_identifiers($alias);
        }
        return $this;
    }
    /**
     * Sets update fields for upsert, update
     *
     * @param list<RawSql>|list<string>|string $set
     * @param bool                             $addToDefault adds update fields to the default ones
     * @param array|null                       $ignore       ignores items in set
     *
     * @return $this
     */
    public function update_fields($set, bool $add_to_default = false, ?array $ignore = null)
    {
        if (!empty($set)) {
            if (!is_array($set)) {
                $set = explode(',', $set);
            }
            foreach ($set as $key => $value) {
                if (!$value instanceof Raw_Sql) {
                    $value = $this->db->protect_identifiers($value);
                }
                if (is_numeric($key)) {
                    $key = $value;
                }
                if ($ignore === null || !in_array($key, $ignore, true)) {
                    if ($add_to_default) {
                        $this->qb_options['updateFieldsAdditional'][$this->db->protect_identifiers($key)] = $value;
                    } else {
                        $this->qb_options['updateFields'][$this->db->protect_identifiers($key)] = $value;
                    }
                }
            }
            if ($add_to_default === false && isset($this->qb_options['updateFieldsAdditional'], $this->qb_options['updateFields'])) {
                $this->qb_options['updateFields'] = array_merge($this->qb_options['updateFields'], $this->qb_options['updateFieldsAdditional']);
                unset($this->qb_options['updateFieldsAdditional']);
            }
        }
        return $this;
    }
    /**
     * Sets constraints for batch upsert, update
     *
     * @param array|RawSql|string $set a string of columns, key value pairs, or RawSql
     *
     * @return $this
     */
    public function on_constraint($set)
    {
        if (!empty($set)) {
            if (is_string($set)) {
                $set = explode(',', $set);
                $set = array_map(trim(...), $set);
            }
            if ($set instanceof Raw_Sql) {
                $set = [$set];
            }
            foreach ($set as $key => $value) {
                if (!$value instanceof Raw_Sql) {
                    $value = $this->db->protect_identifiers($value);
                }
                if (is_string($key)) {
                    $key = $this->db->protect_identifiers($key);
                }
                $this->qb_options['constraints'][$key] = $value;
            }
        }
        return $this;
    }
    /**
     * Sets data source as a query for insertBatch()/updateBatch()/upsertBatch()/deleteBatch()
     *
     * @param BaseBuilder|RawSql $query
     * @param array|string|null  $columns an array or comma delimited string of columns
     */
    public function set_query_as_data($query, ?string $alias = null, $columns = null): Base_Builder
    {
        if (is_string($query)) {
            throw new InvalidArgumentException('$query parameter must be BaseBuilder or RawSql class.');
        }
        if ($query instanceof Base_Builder) {
            $query = $query->get_compiled_select();
        } elseif ($query instanceof Raw_Sql) {
            $query = $query->__toString();
        }
        if (is_string($query)) {
            if ($columns !== null && is_string($columns)) {
                $columns = explode(',', $columns);
                $columns = array_map(trim(...), $columns);
            }
            $columns = (array) $columns;
            if ($columns === []) {
                $columns = $this->fields_from_query($query);
            }
            if ($alias !== null) {
                $this->set_alias($alias);
            }
            foreach ($columns as $key => $value) {
                $columns[$key] = $this->db->escape_char . $value . $this->db->escape_char;
            }
            $this->qb_options['setQueryAsData'] = $query;
            $this->qb_keys = $columns;
            $this->qb_set = [];
        }
        return $this;
    }
    /**
     * Gets column names from a select query
     */
    protected function fields_from_query(string $sql): array
    {
        return $this->db->query('SELECT * FROM (' . $sql . ') _u_ LIMIT 1')->get_field_names();
    }
    /**
     * Converts value array of array to array of strings
     */
    protected function format_values(array $values): array
    {
        return array_map(static fn($index): string => '(' . implode(',', $index) . ')', $values);
    }
    /**
     * Compiles batch insert strings and runs the queries
     *
     * @param array|object|null $set a dataset
     *
     * @return false|int|list<string> Number of rows inserted or FALSE on no data to perform an insert operation, SQL array when testMode
     */
    public function insert_batch($set = null, ?bool $escape = null, int $batch_size = 100)
    {
        if (isset($this->qb_options['setQueryAsData'])) {
            $sql = $this->_insert_batch($this->qb_from[0], $this->qb_keys, []);
            if ($sql === '') {
                return false;
                // @codeCoverageIgnore
            }
            if ($this->test_mode === false) {
                $this->db->query($sql, null, false);
            }
            $this->reset_write();
            return $this->test_mode ? $sql : $this->db->affected_rows();
        }
        if ($set !== null && $set !== []) {
            $this->set_data($set, $escape);
        }
        return $this->batch_execute('_insertBatch', $batch_size);
    }
    /**
     * Generates a platform-specific insert string from the supplied data.
     *
     * @used-by batchExecute()
     *
     * @param string                 $table  Protected table name
     * @param list<string>           $keys   QBKeys
     * @param list<list<int|string>> $values QBSet
     */
    protected function _insert_batch(string $table, array $keys, array $values): string
    {
        $sql = $this->qb_options['sql'] ?? '';
        // if this is the first iteration of batch then we need to build skeleton sql
        if ($sql === '') {
            $sql = 'INSERT ' . $this->compile_ignore('insert') . 'INTO ' . $table . ' (' . implode(', ', $keys) . ")\n{:_table_:}";
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = 'VALUES ' . implode(', ', $this->format_values($values));
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
    /**
     * Allows key/value pairs to be set for batch inserts
     *
     * @param mixed $key
     *
     * @return $this|null
     *
     * @deprecated
     */
    public function set_insert_batch($key, string $value = '', ?bool $escape = null)
    {
        if (!is_array($key)) {
            $key = [[$key => $value]];
        }
        return $this->set_data($key, $escape);
    }
    /**
     * Compiles an insert query and returns the sql
     *
     * @return bool|string
     *
     * @throws DatabaseException
     */
    public function get_compiled_insert(bool $reset = true)
    {
        if ($this->validate_insert() === false) {
            return false;
        }
        $sql = $this->_insert($this->db->protect_identifiers($this->remove_alias($this->qb_from[0]), true, null, false), array_keys($this->qb_set), array_values($this->qb_set));
        if ($reset) {
            $this->reset_write();
        }
        return $this->compile_final_query($sql);
    }
    /**
     * Compiles an insert string and runs the query
     *
     * @param array|object|null $set
     *
     * @return BaseResult|bool|Query
     *
     * @throws DatabaseException
     */
    public function insert($set = null, ?bool $escape = null)
    {
        if ($set !== null) {
            $this->set($set, '', $escape);
        }
        if ($this->validate_insert() === false) {
            return false;
        }
        $sql = $this->_insert($this->db->protect_identifiers($this->remove_alias($this->qb_from[0]), true, $escape, false), array_keys($this->qb_set), array_values($this->qb_set));
        if (!$this->test_mode) {
            $this->reset_write();
            $result = $this->db->query($sql, $this->binds, false);
            // Clear our binds so we don't eat up memory
            $this->binds = [];
            return $result;
        }
        return false;
    }
    /**
     * @internal This is a temporary solution.
     *
     * @see https://github.com/codeigniter4/CodeIgniter4/pull/5376
     *
     * @TODO Fix a root cause, and this method should be removed.
     */
    protected function remove_alias(string $from): string
    {
        if (str_contains($from, ' ')) {
            // if the alias is written with the AS keyword, remove it
            $from = preg_replace('/\s+AS\s+/i', ' ', $from);
            $parts = explode(' ', $from);
            $from = $parts[0];
        }
        return $from;
    }
    /**
     * This method is used by both insert() and getCompiledInsert() to
     * validate that the there data is actually being set and that table
     * has been chosen to be inserted into.
     *
     * @throws DatabaseException
     */
    protected function validate_insert(): bool
    {
        if (empty($this->qb_set)) {
            if ($this->db->db_debug) {
                throw new Database_Exception('You must use the "set" method to insert an entry.');
            }
            return false;
            // @codeCoverageIgnore
        }
        return true;
    }
    /**
     * Generates a platform-specific insert string from the supplied data
     *
     * @param string           $table         Protected table name
     * @param list<string>     $keys          Keys of QBSet
     * @param list<int|string> $unescapedKeys Values of QBSet
     */
    protected function _insert(string $table, array $keys, array $unescaped_keys): string
    {
        return 'INSERT ' . $this->compile_ignore('insert') . 'INTO ' . $table . ' (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $unescaped_keys) . ')';
    }
    /**
     * Compiles a replace into string and runs the query
     *
     * @return BaseResult|false|Query|string
     *
     * @throws DatabaseException
     */
    public function replace(?array $set = null)
    {
        if ($set !== null) {
            $this->set($set);
        }
        if (empty($this->qb_set)) {
            if ($this->db->db_debug) {
                throw new Database_Exception('You must use the "set" method to update an entry.');
            }
            return false;
            // @codeCoverageIgnore
        }
        $table = $this->qb_from[0];
        $sql = $this->_replace($table, array_keys($this->qb_set), array_values($this->qb_set));
        $this->reset_write();
        return $this->test_mode ? $sql : $this->db->query($sql, $this->binds, false);
    }
    /**
     * Generates a platform-specific replace string from the supplied data
     *
     * @param string           $table  Protected table name
     * @param list<string>     $keys   Keys of QBSet
     * @param list<int|string> $values Values of QBSet
     */
    protected function _replace(string $table, array $keys, array $values): string
    {
        return 'REPLACE INTO ' . $table . ' (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
    }
    /**
     * Groups tables in FROM clauses if needed, so there is no confusion
     * about operator precedence.
     *
     * Note: This is only used (and overridden) by MySQL and SQLSRV.
     */
    protected function _from_tables(): string
    {
        return implode(', ', $this->qb_from);
    }
    /**
     * Compiles an update query and returns the sql
     *
     * @return bool|string
     */
    public function get_compiled_update(bool $reset = true)
    {
        if ($this->validate_update() === false) {
            return false;
        }
        $sql = $this->_update($this->qb_from[0], $this->qb_set);
        if ($reset) {
            $this->reset_write();
        }
        return $this->compile_final_query($sql);
    }
    /**
     * Compiles an update string and runs the query.
     *
     * @param array|object|null        $set
     * @param array|RawSql|string|null $where
     *
     * @throws DatabaseException
     */
    public function update($set = null, $where = null, ?int $limit = null): bool
    {
        if ($set !== null) {
            $this->set($set);
        }
        if ($this->validate_update() === false) {
            return false;
        }
        if ($where !== null) {
            $this->where($where);
        }
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if ($limit_zero_as_all && $limit === 0) {
            $limit = null;
        }
        if ($limit !== null) {
            if (!$this->can_limit_where_updates) {
                throw new Database_Exception('This driver does not allow LIMITs on UPDATE queries using WHERE.');
            }
            $this->limit($limit);
        }
        $sql = $this->_update($this->qb_from[0], $this->qb_set);
        if (!$this->test_mode) {
            $this->reset_write();
            $result = $this->db->query($sql, $this->binds, false);
            if ($result !== false) {
                // Clear our binds so we don't eat up memory
                $this->binds = [];
                return true;
            }
            return false;
        }
        return true;
    }
    /**
     * Generates a platform-specific update string from the supplied data
     *
     * @param string                $table  Protected table name
     * @param array<string, string> $values QBSet
     */
    protected function _update(string $table, array $values): string
    {
        $val_str = [];
        foreach ($values as $key => $val) {
            $val_str[] = $key . ' = ' . $val;
        }
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if ($limit_zero_as_all) {
            return 'UPDATE ' . $this->compile_ignore('update') . $table . ' SET ' . implode(', ', $val_str) . $this->compile_where_having('QBWhere') . $this->compile_order_by() . ($this->qb_limit ? $this->_limit(' ', true) : '');
        }
        return 'UPDATE ' . $this->compile_ignore('update') . $table . ' SET ' . implode(', ', $val_str) . $this->compile_where_having('QBWhere') . $this->compile_order_by() . ($this->qb_limit !== false ? $this->_limit(' ', true) : '');
    }
    /**
     * This method is used by both update() and getCompiledUpdate() to
     * validate that data is actually being set and that a table has been
     * chosen to be updated.
     *
     * @throws DatabaseException
     */
    protected function validate_update(): bool
    {
        if (empty($this->qb_set)) {
            if ($this->db->db_debug) {
                throw new Database_Exception('You must use the "set" method to update an entry.');
            }
            return false;
            // @codeCoverageIgnore
        }
        return true;
    }
    /**
     * Sets data and calls batchExecute to run queries
     *
     * @param array|object|null        $set         a dataset
     * @param array|RawSql|string|null $constraints
     *
     * @return false|int|list<string> Number of rows affected or FALSE on failure, SQL array when testMode
     */
    public function update_batch($set = null, $constraints = null, int $batch_size = 100)
    {
        $this->on_constraint($constraints);
        if (isset($this->qb_options['setQueryAsData'])) {
            $sql = $this->_update_batch($this->qb_from[0], $this->qb_keys, []);
            if ($sql === '') {
                return false;
                // @codeCoverageIgnore
            }
            if ($this->test_mode === false) {
                $this->db->query($sql, null, false);
            }
            $this->reset_write();
            return $this->test_mode ? $sql : $this->db->affected_rows();
        }
        if ($set !== null && $set !== []) {
            $this->set_data($set, true);
        }
        return $this->batch_execute('_updateBatch', $batch_size);
    }
    /**
     * Generates a platform-specific batch update string from the supplied data
     *
     * @used-by batchExecute()
     *
     * @param string                 $table  Protected table name
     * @param list<string>           $keys   QBKeys
     * @param list<list<int|string>> $values QBSet
     */
    protected function _update_batch(string $table, array $keys, array $values): string
    {
        $sql = $this->qb_options['sql'] ?? '';
        // if this is the first iteration of batch then we need to build skeleton sql
        if ($sql === '') {
            $constraints = $this->qb_options['constraints'] ?? [];
            if ($constraints === []) {
                if ($this->db->db_debug) {
                    throw new Database_Exception('You must specify a constraint to match on for batch updates.');
                    // @codeCoverageIgnore
                }
                return '';
                // @codeCoverageIgnore
            }
            $update_fields = $this->qb_options['updateFields'] ?? $this->update_fields($keys, false, $constraints)->qb_options['updateFields'] ?? [];
            $alias = $this->qb_options['alias'] ?? '_u';
            $sql = 'UPDATE ' . $this->compile_ignore('update') . $table . "\n";
            $sql .= "SET\n";
            $sql .= implode(",\n", array_map(static fn($key, $value): string => $key . ($value instanceof Raw_Sql ? ' = ' . $value : ' = ' . $alias . '.' . $value), array_keys($update_fields), $update_fields)) . "\n";
            $sql .= "FROM (\n{:_table_:}";
            $sql .= ') ' . $alias . "\n";
            $sql .= 'WHERE ' . implode(' AND ', array_map(static fn($key, $value) => $value instanceof Raw_Sql && is_string($key) ? $table . '.' . $key . ' = ' . $value : ($value instanceof Raw_Sql ? $value : $table . '.' . $value . ' = ' . $alias . '.' . $value), array_keys($constraints), $constraints));
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = implode(" UNION ALL\n", array_map(static fn($value): string => 'SELECT ' . implode(', ', array_map(static fn($key, $index): string => $index . ' ' . $key, $keys, $value)), $values)) . "\n";
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
    /**
     * Allows key/value pairs to be set for batch updating
     *
     * @param array|object $key
     *
     * @return $this
     *
     * @throws DatabaseException
     *
     * @deprecated
     */
    public function set_update_batch($key, string $index = '', ?bool $escape = null)
    {
        if ($index !== '') {
            $this->on_constraint($index);
        }
        $this->set_data($key, $escape);
        return $this;
    }
    /**
     * Compiles a delete string and runs "DELETE FROM table"
     *
     * @return bool|string TRUE on success, FALSE on failure, string on testMode
     */
    public function empty_table()
    {
        $table = $this->qb_from[0];
        $sql = $this->_delete($table);
        if ($this->test_mode) {
            return $sql;
        }
        $this->reset_write();
        return $this->db->query($sql, null, false);
    }
    /**
     * Compiles a truncate string and runs the query
     * If the database does not support the truncate() command
     * This function maps to "DELETE FROM table"
     *
     * @return bool|string TRUE on success, FALSE on failure, string on testMode
     */
    public function truncate()
    {
        $table = $this->qb_from[0];
        $sql = $this->_truncate($table);
        if ($this->test_mode) {
            return $sql;
        }
        $this->reset_write();
        return $this->db->query($sql, null, false);
    }
    /**
     * Generates a platform-specific truncate string from the supplied data
     *
     * If the database does not support the truncate() command,
     * then this method maps to 'DELETE FROM table'
     *
     * @param string $table Protected table name
     */
    protected function _truncate(string $table): string
    {
        return 'TRUNCATE ' . $table;
    }
    /**
     * Compiles a delete query string and returns the sql
     */
    public function get_compiled_delete(bool $reset = true): string
    {
        $sql = $this->test_mode()->delete('', null, $reset);
        $this->test_mode(false);
        return $this->compile_final_query($sql);
    }
    /**
     * Compiles a delete string and runs the query
     *
     * @param array|RawSql|string $where
     *
     * @return bool|string Returns a SQL string if in test mode.
     *
     * @throws DatabaseException
     */
    public function delete($where = '', ?int $limit = null, bool $reset_data = true)
    {
        $table = $this->db->protect_identifiers($this->qb_from[0], true, null, false);
        if ($where !== '') {
            $this->where($where);
        }
        if (empty($this->qb_where)) {
            if ($this->db->db_debug) {
                throw new Database_Exception('Deletes are not allowed unless they contain a "where" or "like" clause.');
            }
            return false;
            // @codeCoverageIgnore
        }
        $sql = $this->_delete($this->remove_alias($table));
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if ($limit_zero_as_all && $limit === 0) {
            $limit = null;
        }
        if ($limit !== null) {
            $this->qb_limit = $limit;
        }
        if (!empty($this->qb_limit)) {
            if (!$this->can_limit_deletes) {
                throw new Database_Exception('SQLite3 does not allow LIMITs on DELETE queries.');
            }
            $sql = $this->_limit($sql, true);
        }
        if ($reset_data) {
            $this->reset_write();
        }
        return $this->test_mode ? $sql : $this->db->query($sql, $this->binds, false);
    }
    /**
     * Sets data and calls batchExecute to run queries
     *
     * @param array|object|null $set         a dataset
     * @param array|RawSql|null $constraints
     *
     * @return false|int|list<string> Number of rows affected or FALSE on failure, SQL array when testMode
     */
    public function delete_batch($set = null, $constraints = null, int $batch_size = 100)
    {
        $this->on_constraint($constraints);
        if (isset($this->qb_options['setQueryAsData'])) {
            $sql = $this->_delete_batch($this->qb_from[0], $this->qb_keys, []);
            if ($sql === '') {
                return false;
                // @codeCoverageIgnore
            }
            if ($this->test_mode === false) {
                $this->db->query($sql, null, false);
            }
            $this->reset_write();
            return $this->test_mode ? $sql : $this->db->affected_rows();
        }
        if ($set !== null && $set !== []) {
            $this->set_data($set, true);
        }
        return $this->batch_execute('_deleteBatch', $batch_size);
    }
    /**
     * Generates a platform-specific batch update string from the supplied data
     *
     * @used-by batchExecute()
     *
     * @param string           $table  Protected table name
     * @param list<string>     $keys   QBKeys
     * @param list<int|string> $values QBSet
     */
    protected function _delete_batch(string $table, array $keys, array $values): string
    {
        $sql = $this->qb_options['sql'] ?? '';
        // if this is the first iteration of batch then we need to build skeleton sql
        if ($sql === '') {
            $constraints = $this->qb_options['constraints'] ?? [];
            if ($constraints === []) {
                if ($this->db->db_debug) {
                    throw new Database_Exception('You must specify a constraint to match on for batch deletes.');
                    // @codeCoverageIgnore
                }
                return '';
                // @codeCoverageIgnore
            }
            $alias = $this->qb_options['alias'] ?? '_u';
            $sql = 'DELETE ' . $table . ' FROM ' . $table . "\n";
            $sql .= "INNER JOIN (\n{:_table_:}";
            $sql .= ') ' . $alias . "\n";
            $sql .= 'ON ' . implode(' AND ', array_map(static fn($key, $value) => $value instanceof Raw_Sql ? $value : (is_string($key) ? $table . '.' . $key . ' = ' . $alias . '.' . $value : $table . '.' . $value . ' = ' . $alias . '.' . $value), array_keys($constraints), $constraints));
            // convert binds in where
            foreach ($this->qb_where as $key => $where) {
                foreach ($this->binds as $field => $bind) {
                    $this->qb_where[$key]['condition'] = str_replace(':' . $field . ':', $bind[0], $where['condition']);
                }
            }
            $sql .= ' ' . $this->compile_where_having('QBWhere');
            $this->qb_options['sql'] = trim($sql);
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = implode(" UNION ALL\n", array_map(static fn($value): string => 'SELECT ' . implode(', ', array_map(static fn($key, $index): string => $index . ' ' . $key, $keys, $value)), $values)) . "\n";
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
    /**
     * Increments a numeric column by the specified value.
     *
     * @return bool
     */
    public function increment(string $column, int $value = 1)
    {
        $column = $this->db->protect_identifiers($column);
        $sql = $this->_update($this->qb_from[0], [$column => "{$column} + {$value}"]);
        if (!$this->test_mode) {
            $this->reset_write();
            return $this->db->query($sql, $this->binds, false);
        }
        return true;
    }
    /**
     * Decrements a numeric column by the specified value.
     *
     * @return bool
     */
    public function decrement(string $column, int $value = 1)
    {
        $column = $this->db->protect_identifiers($column);
        $sql = $this->_update($this->qb_from[0], [$column => "{$column}-{$value}"]);
        if (!$this->test_mode) {
            $this->reset_write();
            return $this->db->query($sql, $this->binds, false);
        }
        return true;
    }
    /**
     * Generates a platform-specific delete string from the supplied data
     *
     * @param string $table Protected table name
     */
    protected function _delete(string $table): string
    {
        return 'DELETE ' . $this->compile_ignore('delete') . 'FROM ' . $table . $this->compile_where_having('QBWhere');
    }
    /**
     * Used to track SQL statements written with aliased tables.
     *
     * @param array|string $table The table to inspect
     *
     * @return string|null
     */
    protected function track_aliases($table)
    {
        if (is_array($table)) {
            foreach ($table as $t) {
                $this->track_aliases($t);
            }
            return null;
        }
        // Does the string contain a comma?  If so, we need to separate
        // the string into discreet statements
        if (str_contains($table, ',')) {
            return $this->track_aliases(explode(',', $table));
        }
        // if a table alias is used we can recognize it by a space
        if (str_contains($table, ' ')) {
            // if the alias is written with the AS keyword, remove it
            $table = preg_replace('/\s+AS\s+/i', ' ', $table);
            // Grab the alias
            $alias = trim(strrchr($table, ' '));
            // Store the alias, if it doesn't already exist
            $this->db->add_table_alias($alias);
        }
        return null;
    }
    /**
     * Compile the SELECT statement
     *
     * Generates a query string based on which functions were used.
     * Should not be called directly.
     *
     * @param mixed $selectOverride
     */
    protected function compile_select($select_override = false): string
    {
        if ($select_override !== false) {
            $sql = $select_override;
        } else {
            $sql = $this->qb_distinct ? 'SELECT DISTINCT ' : 'SELECT ';
            if (empty($this->qb_select)) {
                $sql .= '*';
            } else {
                // Cycle through the "select" portion of the query and prep each column name.
                // The reason we protect identifiers here rather than in the select() function
                // is because until the user calls the from() function we don't know if there are aliases
                foreach ($this->qb_select as $key => $val) {
                    if ($val instanceof Raw_Sql) {
                        $this->qb_select[$key] = (string) $val;
                    } else {
                        $protect = $this->qb_no_escape[$key] ?? null;
                        $this->qb_select[$key] = $this->db->protect_identifiers($val, false, $protect);
                    }
                }
                $sql .= implode(', ', $this->qb_select);
            }
        }
        if (!empty($this->qb_from)) {
            $sql .= "\nFROM " . $this->_from_tables();
        }
        if (!empty($this->qb_join)) {
            $sql .= "\n" . implode("\n", $this->qb_join);
        }
        $sql .= $this->compile_where_having('QBWhere') . $this->compile_group_by() . $this->compile_where_having('QBHaving') . $this->compile_order_by();
        $limit_zero_as_all = config(Feature::class)->limit_zero_as_all ?? true;
        if ($limit_zero_as_all) {
            if ($this->qb_limit) {
                $sql = $this->_limit($sql . "\n");
            }
        } elseif ($this->qb_limit !== false || $this->qb_offset) {
            $sql = $this->_limit($sql . "\n");
        }
        return $this->union_injection($sql);
    }
    /**
     * Checks if the ignore option is supported by
     * the Database Driver for the specific statement.
     *
     * @return string
     */
    protected function compile_ignore(string $statement)
    {
        if ($this->qb_ignore && isset($this->supported_ignore_statements[$statement])) {
            return trim($this->supported_ignore_statements[$statement]) . ' ';
        }
        return '';
    }
    /**
     * Escapes identifiers in WHERE and HAVING statements at execution time.
     *
     * Required so that aliases are tracked properly, regardless of whether
     * where(), orWhere(), having(), orHaving are called prior to from(),
     * join() and prefixTable is added only if needed.
     *
     * @param string $qbKey 'QBWhere' or 'QBHaving'
     *
     * @return string SQL statement
     */
    protected function compile_where_having(string $qb_key): string
    {
        if (!empty($this->{$qb_key})) {
            foreach ($this->{$qb_key} as &$qbkey) {
                // Is this condition already compiled?
                if (is_string($qbkey)) {
                    continue;
                }
                if ($qbkey instanceof Raw_Sql) {
                    continue;
                }
                if ($qbkey['condition'] instanceof Raw_Sql) {
                    $qbkey = $qbkey['condition'];
                    continue;
                }
                if ($qbkey['escape'] === false) {
                    $qbkey = $qbkey['condition'];
                    continue;
                }
                // Split multiple conditions
                $conditions = preg_split('/((?:^|\s+)AND\s+|(?:^|\s+)OR\s+)/i', $qbkey['condition'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                foreach ($conditions as &$condition) {
                    $op = $this->get_operator($condition);
                    if ($op === false || preg_match('/^(\(?)(.*)(' . preg_quote($op, '/') . ')\s*(.*(?<!\)))?(\)?)$/i', $condition, $matches) !== 1) {
                        continue;
                    }
                    // $matches = [
                    //  0 => '(test <= foo)',   /* the whole thing */
                    //  1 => '(',               /* optional */
                    //  2 => 'test',            /* the field name */
                    //  3 => ' <= ',            /* $op */
                    //  4 => 'foo',	            /* optional, if $op is e.g. 'IS NULL' */
                    //  5 => ')'                /* optional */
                    // ];
                    if ($matches[4] !== '') {
                        $protect_identifiers = false;
                        if (str_contains($matches[4], '.')) {
                            $protect_identifiers = true;
                        }
                        if (!str_contains($matches[4], ':')) {
                            $matches[4] = $this->db->protect_identifiers(trim($matches[4]), false, $protect_identifiers);
                        }
                        $matches[4] = ' ' . $matches[4];
                    }
                    $condition = $matches[1] . $this->db->protect_identifiers(trim($matches[2])) . ' ' . trim($matches[3]) . $matches[4] . $matches[5];
                }
                $qbkey = implode('', $conditions);
            }
            return ($qb_key === 'QBHaving' ? "\nHAVING " : "\nWHERE ") . implode("\n", $this->{$qb_key});
        }
        return '';
    }
    /**
     * Escapes identifiers in GROUP BY statements at execution time.
     *
     * Required so that aliases are tracked properly, regardless of whether
     * groupBy() is called prior to from(), join() and prefixTable is added
     * only if needed.
     */
    protected function compile_group_by(): string
    {
        if (!empty($this->qb_group_by)) {
            foreach ($this->qb_group_by as &$group_by) {
                // Is it already compiled?
                if (is_string($group_by)) {
                    continue;
                }
                $group_by = $group_by['escape'] === false || $this->is_literal($group_by['field']) ? $group_by['field'] : $this->db->protect_identifiers($group_by['field']);
            }
            return "\nGROUP BY " . implode(', ', $this->qb_group_by);
        }
        return '';
    }
    /**
     * Escapes identifiers in ORDER BY statements at execution time.
     *
     * Required so that aliases are tracked properly, regardless of whether
     * orderBy() is called prior to from(), join() and prefixTable is added
     * only if needed.
     */
    protected function compile_order_by(): string
    {
        if (is_array($this->qb_order_by) && $this->qb_order_by !== []) {
            foreach ($this->qb_order_by as &$order_by) {
                if (is_string($order_by)) {
                    continue;
                }
                if ($order_by['escape'] !== false && !$this->is_literal($order_by['field'])) {
                    $order_by['field'] = $this->db->protect_identifiers($order_by['field']);
                }
                $order_by = $order_by['field'] . $order_by['direction'];
            }
            return "\nORDER BY " . implode(', ', $this->qb_order_by);
        }
        return '';
    }
    protected function union_injection(string $sql): string
    {
        if ($this->qb_union === []) {
            return $sql;
        }
        return 'SELECT * FROM (' . $sql . ') ' . ($this->db->protect_identifiers ? $this->db->escape_identifiers('uwrp0') : 'uwrp0') . implode("\n", $this->qb_union);
    }
    /**
     * Takes an object as input and converts the class variables to array key/vals
     *
     * @param array|object $object
     *
     * @return array
     */
    protected function object_to_array($object)
    {
        if (!is_object($object)) {
            return $object;
        }
        if ($object instanceof Raw_Sql) {
            throw new InvalidArgumentException('RawSql "' . $object . '" cannot be used here.');
        }
        $array = [];
        foreach (get_object_vars($object) as $key => $val) {
            if ((!is_object($val) || $val instanceof Raw_Sql) && !is_array($val)) {
                $array[$key] = $val;
            }
        }
        return $array;
    }
    /**
     * Takes an object as input and converts the class variables to array key/vals
     *
     * @param array|object $object
     *
     * @return array
     */
    protected function batch_object_to_array($object)
    {
        if (!is_object($object)) {
            return $object;
        }
        $array = [];
        $out = get_object_vars($object);
        $fields = array_keys($out);
        foreach ($fields as $val) {
            $i = 0;
            foreach ($out[$val] as $data) {
                $array[$i++][$val] = $data;
            }
        }
        return $array;
    }
    /**
     * Determines if a string represents a literal value or a field name
     */
    protected function is_literal(string $str): bool
    {
        $str = trim($str);
        if ($str === '' || ctype_digit($str) || (string) (float) $str === $str || in_array(strtoupper($str), ['TRUE', 'FALSE'], true)) {
            return true;
        }
        if ($this->is_literal_str === []) {
            $this->is_literal_str = $this->db->escape_char !== '"' ? ['"', "'"] : ["'"];
        }
        return in_array($str[0], $this->is_literal_str, true);
    }
    /**
     * Publicly-visible method to reset the QB values.
     *
     * @return $this
     */
    public function reset_query()
    {
        $this->reset_select();
        $this->reset_write();
        return $this;
    }
    /**
     * Resets the query builder values.  Called by the get() function
     *
     * @param array $qbResetItems An array of fields to reset
     *
     * @return void
     */
    protected function reset_run(array $qb_reset_items)
    {
        foreach ($qb_reset_items as $item => $default_value) {
            $this->{$item} = $default_value;
        }
    }
    /**
     * Resets the query builder values.  Called by the get() function
     *
     * @return void
     */
    protected function reset_select()
    {
        $this->reset_run(['QBSelect' => [], 'QBJoin' => [], 'QBWhere' => [], 'QBGroupBy' => [], 'QBHaving' => [], 'QBOrderBy' => [], 'QBNoEscape' => [], 'QBDistinct' => false, 'QBLimit' => false, 'QBOffset' => false, 'QBUnion' => []]);
        if ($this->db instanceof Base_Connection) {
            $this->db->set_aliased_tables([]);
        }
        // Reset QBFrom part
        if (!empty($this->qb_from)) {
            $this->from(array_shift($this->qb_from), true);
        }
    }
    /**
     * Resets the query builder "write" values.
     *
     * Called by the insert() update() insertBatch() updateBatch() and delete() functions
     *
     * @return void
     */
    protected function reset_write()
    {
        $this->reset_run(['QBSet' => [], 'QBJoin' => [], 'QBWhere' => [], 'QBOrderBy' => [], 'QBKeys' => [], 'QBLimit' => false, 'QBIgnore' => false, 'QBOptions' => []]);
    }
    /**
     * Tests whether the string has an SQL operator
     */
    protected function has_operator(string $str): bool
    {
        return preg_match('/(<|>|!|=|\sIS NULL|\sIS NOT NULL|\sEXISTS|\sBETWEEN|\sLIKE|\sIN\s*\(|\s)/i', trim($str)) === 1;
    }
    /**
     * Returns the SQL string operator
     *
     * @return array|false|string
     */
    protected function get_operator(string $str, bool $list = false)
    {
        if ($this->preg_operators === []) {
            $_les = $this->db->like_escape_str !== '' ? '\s+' . preg_quote(trim(sprintf($this->db->like_escape_str, $this->db->like_escape_char)), '/') : '';
            $this->preg_operators = [
                '\s*(?:<|>|!)?=\s*',
                // =, <=, >=, !=
                '\s*<>?\s*',
                // <, <>
                '\s*>\s*',
                // >
                '\s+IS NULL',
                // IS NULL
                '\s+IS NOT NULL',
                // IS NOT NULL
                '\s+EXISTS\s*\(.*\)',
                // EXISTS (sql)
                '\s+NOT EXISTS\s*\(.*\)',
                // NOT EXISTS(sql)
                '\s+BETWEEN\s+',
                // BETWEEN value AND value
                '\s+IN\s*\(.*\)',
                // IN (list)
                '\s+NOT IN\s*\(.*\)',
                // NOT IN (list)
                '\s+LIKE\s+\S.*(' . $_les . ')?',
                // LIKE 'expr'[ ESCAPE '%s']
                '\s+NOT LIKE\s+\S.*(' . $_les . ')?',
            ];
        }
        return preg_match_all('/' . implode('|', $this->preg_operators) . '/i', $str, $match) >= 1 ? $list ? $match[0] : $match[0][0] : false;
    }
    /**
     * Returns the SQL string operator from where key
     *
     * @return false|list<string>
     */
    private function get_operator_from_where_key(string $where_key)
    {
        $where_key = trim($where_key);
        $preg_operators = [
            '\s*(?:<|>|!)?=',
            // =, <=, >=, !=
            '\s*<>?',
            // <, <>
            '\s*>',
            // >
            '\s+IS NULL',
            // IS NULL
            '\s+IS NOT NULL',
            // IS NOT NULL
            '\s+EXISTS\s*\(.*\)',
            // EXISTS (sql)
            '\s+NOT EXISTS\s*\(.*\)',
            // NOT EXISTS (sql)
            '\s+BETWEEN\s+',
            // BETWEEN value AND value
            '\s+IN\s*\(.*\)',
            // IN (list)
            '\s+NOT IN\s*\(.*\)',
            // NOT IN (list)
            '\s+LIKE',
            // LIKE
            '\s+NOT LIKE',
        ];
        return preg_match_all('/' . implode('|', $preg_operators) . '/i', $where_key, $match) >= 1 ? $match[0] : false;
    }
    /**
     * Stores a bind value after ensuring that it's unique.
     * While it might be nicer to have named keys for our binds array
     * with PHP 7+ we get a huge memory/performance gain with indexed
     * arrays instead, so lets take advantage of that here.
     *
     * @param mixed $value
     */
    protected function set_bind(string $key, $value = null, bool $escape = true): string
    {
        if (!array_key_exists($key, $this->binds)) {
            $this->binds[$key] = [$value, $escape];
            return $key;
        }
        if (!array_key_exists($key, $this->binds_key_count)) {
            $this->binds_key_count[$key] = 1;
        }
        $count = $this->binds_key_count[$key]++;
        $this->binds[$key . '.' . $count] = [$value, $escape];
        return $key . '.' . $count;
    }
    /**
     * Returns a clone of a Base Builder with reset query builder values.
     *
     * @return $this
     *
     * @deprecated
     */
    protected function clean_clone()
    {
        return (clone $this)->from([], true)->reset_query();
    }
    /**
     * @param mixed $value
     */
    protected function is_subquery($value): bool
    {
        return $value instanceof Base_Builder || $value instanceof Closure;
    }
    /**
     * @param BaseBuilder|Closure(BaseBuilder): BaseBuilder $builder
     * @param bool                                          $wrapped Wrap the subquery in brackets
     * @param string                                        $alias   Subquery alias
     */
    protected function build_subquery($builder, bool $wrapped = false, string $alias = ''): string
    {
        if ($builder instanceof Closure) {
            $builder($builder = $this->db->new_query());
        }
        if ($builder === $this) {
            throw new Database_Exception('The subquery cannot be the same object as the main query object.');
        }
        $subquery = strtr($builder->get_compiled_select(false), "\n", ' ');
        if ($wrapped) {
            $subquery = '(' . $subquery . ')';
            $alias = trim($alias);
            if ($alias !== '') {
                $subquery .= ' ' . ($this->db->protect_identifiers ? $this->db->escape_identifiers($alias) : $alias);
            }
        }
        return $subquery;
    }
}