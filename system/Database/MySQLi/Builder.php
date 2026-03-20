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
namespace Code_Igniter\Database\My_Sq_Li;

use Code_Igniter\Database\Base_Builder;
use Code_Igniter\Database\Exceptions\Database_Exception;
use Code_Igniter\Database\Raw_Sql;
/**
 * Builder for MySQLi
 */
class Builder extends Base_Builder
{
    /**
     * Identifier escape character
     *
     * @var string
     */
    protected $escape_char = '`';
    /**
     * Specifies which sql statements
     * support the ignore option.
     *
     * @var array<string, string>
     */
    protected $supported_ignore_statements = ['update' => 'IGNORE', 'insert' => 'IGNORE', 'delete' => 'IGNORE'];
    /**
     * FROM tables
     *
     * Groups tables in FROM clauses if needed, so there is no confusion
     * about operator precedence.
     *
     * Note: This is only used (and overridden) by MySQL.
     */
    protected function _from_tables(): string
    {
        if ($this->qb_join !== [] && count($this->qb_from) > 1) {
            return '(' . implode(', ', $this->qb_from) . ')';
        }
        return implode(', ', $this->qb_from);
    }
    /**
     * Generates a platform-specific batch update string from the supplied data
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
            $alias = $this->qb_options['alias'] ?? '`_u`';
            $sql = 'UPDATE ' . $this->compile_ignore('update') . $table . "\n";
            $sql .= "INNER JOIN (\n{:_table_:}";
            $sql .= ') ' . $alias . "\n";
            $sql .= 'ON ' . implode(' AND ', array_map(static fn($key, $value) => $value instanceof Raw_Sql && is_string($key) ? $table . '.' . $key . ' = ' . $value : ($value instanceof Raw_Sql ? $value : $table . '.' . $value . ' = ' . $alias . '.' . $value), array_keys($constraints), $constraints)) . "\n";
            $sql .= "SET\n";
            $sql .= implode(",\n", array_map(static fn($key, $value): string => $table . '.' . $key . ($value instanceof Raw_Sql ? ' = ' . $value : ' = ' . $alias . '.' . $value), array_keys($update_fields), $update_fields));
            $this->qb_options['sql'] = $sql;
        }
        if (isset($this->qb_options['setQueryAsData'])) {
            $data = $this->qb_options['setQueryAsData'];
        } else {
            $data = implode(" UNION ALL\n", array_map(static fn($value): string => 'SELECT ' . implode(', ', array_map(static fn($key, $index): string => $index . ' ' . $key, $keys, $value)), $values)) . "\n";
        }
        return str_replace('{:_table_:}', $data, $sql);
    }
}