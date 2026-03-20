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
namespace Code_Igniter\HTTP\Files;

use Recursive_Array_Iterator;
use Recursive_Iterator_Iterator;
/**
 * Class FileCollection
 *
 * Provides easy access to uploaded files for a request.
 *
 * @see \CodeIgniter\HTTP\Files\FileCollectionTest
 */
class File_Collection
{
    /**
     * An array of UploadedFile instances for any files
     * uploaded as part of this request.
     * Populated the first time either files(), file(), or hasFile()
     * is called.
     *
     * @var array|null
     */
    protected $files;
    /**
     * Returns an array of all uploaded files that were found.
     * Each element in the array will be an instance of UploadedFile.
     * The key of each element will be the client filename.
     *
     * @return array|null
     */
    public function all()
    {
        $this->populate_files();
        return $this->files;
    }
    /**
     * Attempts to get a single file from the collection of uploaded files.
     *
     * @return UploadedFile|null
     */
    public function get_file(string $name)
    {
        $this->populate_files();
        if ($this->has_file($name)) {
            if (str_contains($name, '.')) {
                $name = explode('.', $name);
                $uploaded_file = $this->get_value_dot_notation_syntax($name, $this->files);
                return $uploaded_file instanceof Uploaded_File ? $uploaded_file : null;
            }
            if (array_key_exists($name, $this->files)) {
                $uploaded_file = $this->files[$name];
                return $uploaded_file instanceof Uploaded_File ? $uploaded_file : null;
            }
        }
        return null;
    }
    /**
     * Verify if a file exist in the collection of uploaded files and is have been uploaded with multiple option.
     *
     * @return list<UploadedFile>|null
     */
    public function get_file_multiple(string $name)
    {
        $this->populate_files();
        if ($this->has_file($name)) {
            if (str_contains($name, '.')) {
                $name = explode('.', $name);
                $uploaded_file = $this->get_value_dot_notation_syntax($name, $this->files);
                return is_array($uploaded_file) && $uploaded_file[array_key_first($uploaded_file)] instanceof Uploaded_File ? $uploaded_file : null;
            }
            if (array_key_exists($name, $this->files)) {
                $uploaded_file = $this->files[$name];
                return is_array($uploaded_file) && $uploaded_file[array_key_first($uploaded_file)] instanceof Uploaded_File ? $uploaded_file : null;
            }
        }
        return null;
    }
    /**
     * Checks whether an uploaded file with name $fileID exists in
     * this request.
     *
     * @param string $fileID The name of the uploaded file (from the input)
     */
    public function has_file(string $file_id): bool
    {
        $this->populate_files();
        if (str_contains($file_id, '.')) {
            $segments = explode('.', $file_id);
            $el = $this->files;
            foreach ($segments as $segment) {
                if (!array_key_exists($segment, $el)) {
                    return false;
                }
                $el = $el[$segment];
            }
            return true;
        }
        return isset($this->files[$file_id]);
    }
    /**
     * Taking information from the $_FILES array, it creates an instance
     * of UploadedFile for each one, saving the results to this->files.
     *
     * Called by files(), file(), and hasFile()
     *
     * @return void
     */
    protected function populate_files()
    {
        if (is_array($this->files)) {
            return;
        }
        $this->files = [];
        $files = service('superglobals')->get_files_array();
        if ($files === []) {
            return;
        }
        $files = $this->fix_files_array($files);
        foreach ($files as $name => $file) {
            $this->files[$name] = $this->create_file_object($file);
        }
    }
    /**
     * Given a file array, will create UploadedFile instances. Will
     * loop over an array and create objects for each.
     *
     * @return list<UploadedFile>|UploadedFile
     */
    protected function create_file_object(array $array)
    {
        if (!isset($array['name'])) {
            $output = [];
            foreach ($array as $key => $values) {
                if (!is_array($values)) {
                    continue;
                }
                $output[$key] = $this->create_file_object($values);
            }
            return $output;
        }
        return new Uploaded_File($array['tmp_name'] ?? null, $array['name'] ?? null, $array['type'] ?? null, ($array['size'] ?? null) === null ? null : (int) $array['size'], $array['error'] ?? null, $array['full_path'] ?? null);
    }
    /**
     * Reformats the odd $_FILES array into something much more like
     * we would expect, with each object having its own array.
     *
     * Thanks to Jack Sleight on the PHP Manual page for the basis
     * of this method.
     *
     * @see http://php.net/manual/en/reserved.variables.files.php#118294
     */
    protected function fix_files_array(array $data): array
    {
        $output = [];
        foreach ($data as $name => $array) {
            foreach ($array as $field => $value) {
                $pointer =& $output[$name];
                if (!is_array($value)) {
                    $pointer[$field] = $value;
                    continue;
                }
                $stack = [&$pointer];
                $iterator = new Recursive_Iterator_Iterator(new Recursive_Array_Iterator($value), Recursive_Iterator_Iterator::SELF_FIRST);
                foreach ($iterator as $key => $val) {
                    array_splice($stack, $iterator->get_depth() + 1);
                    $pointer =& $stack[count($stack) - 1];
                    $pointer =& $pointer[$key];
                    $stack[] =& $pointer;
                    // RecursiveIteratorIterator::hasChildren() can be used. RecursiveIteratorIterator
                    // forwards all unknown method calls to the underlying RecursiveIterator internally.
                    // See https://github.com/php/doc-en/issues/787#issuecomment-881446121
                    if (!$iterator->has_children()) {
                        $pointer[$field] = $val;
                    }
                }
            }
        }
        return $output;
    }
    /**
     * Navigate through an array looking for a particular index
     *
     * @param array $index The index sequence we are navigating down
     * @param array $value The portion of the array to process
     *
     * @return list<UploadedFile>|UploadedFile|null
     */
    protected function get_value_dot_notation_syntax(array $index, array $value)
    {
        $current_index = array_shift($index);
        if (isset($current_index) && $index !== [] && array_key_exists($current_index, $value) && is_array($value[$current_index])) {
            return $this->get_value_dot_notation_syntax($index, $value[$current_index]);
        }
        return $value[$current_index] ?? null;
    }
}