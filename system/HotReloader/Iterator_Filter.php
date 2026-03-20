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
namespace Code_Igniter\Hot_Reloader;

use Config\Toolbar;
use Recursive_Filter_Iterator;
use Recursive_Iterator;
/**
 * @internal
 *
 * @psalm-suppress MissingTemplateParam
 */
final class Iterator_Filter extends Recursive_Filter_Iterator implements Recursive_Iterator
{
    private array $watched_extensions = [];
    public function __construct(Recursive_Iterator $iterator)
    {
        parent::__construct($iterator);
        $this->watched_extensions = config(Toolbar::class)->watched_extensions;
    }
    /**
     * Apply filters to the files in the iterator.
     */
    public function accept(): bool
    {
        if (!$this->current()->is_file()) {
            return true;
        }
        $filename = $this->current()->get_filename();
        // Skip hidden files and directories.
        if ($filename[0] === '.') {
            return false;
        }
        // Only consume files of interest.
        $ext = trim(strtolower($this->current()->get_extension()), '. ');
        return in_array($ext, $this->watched_extensions, true);
    }
}