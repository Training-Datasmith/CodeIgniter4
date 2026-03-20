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

use Stringable;
/**
 * @see \CodeIgniter\Database\RawSqlTest
 */
class Raw_Sql implements Stringable
{
    /**
     * @var string Raw SQL string
     */
    private string $string;
    public function __construct(string $sql_string)
    {
        $this->string = $sql_string;
    }
    public function __toString(): string
    {
        return $this->string;
    }
    /**
     * Create new instance with new SQL string
     */
    public function with(string $new_sql_string): self
    {
        $new = clone $this;
        $new->string = $new_sql_string;
        return $new;
    }
    /**
     * Returns unique id for binding key
     */
    public function get_binding_key(): string
    {
        return 'RawSql' . spl_object_id($this);
    }
}