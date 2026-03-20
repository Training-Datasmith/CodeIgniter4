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
namespace Code_Igniter\CLI;

use Config\Generators;
use Throwable;
/**
 * GeneratorTrait contains a collection of methods
 * to build the commands that generates a file.
 */
trait Generator_Trait
{
    /**
     * Component Name
     *
     * @var string
     */
    protected $component;
    /**
     * File directory
     *
     * @var string
     */
    protected $directory;
    /**
     * (Optional) View template path
     *
     * We use special namespaced paths like:
     *      `CodeIgniter\Commands\Generators\Views\cell.tpl.php`.
     */
    protected ?string $template_path = null;
    /**
     * View template name for fallback
     *
     * @var string
     */
    protected $template;
    /**
     * Language string key for required class names.
     *
     * @var string
     */
    protected $class_name_lang = '';
    /**
     * Namespace to use for class.
     * Leave null to use the default namespace.
     */
    protected ?string $namespace = null;
    /**
     * Whether to require class name.
     *
     * @internal
     *
     * @var bool
     */
    private $has_class_name = true;
    /**
     * Whether to sort class imports.
     *
     * @internal
     *
     * @var bool
     */
    private $sort_imports = true;
    /**
     * Whether the `--suffix` option has any effect.
     *
     * @internal
     *
     * @var bool
     */
    private $enabled_suffixing = true;
    /**
     * The params array for easy access by other methods.
     *
     * @internal
     *
     * @var array<int|string, string|null>
     */
    private $params = [];
    /**
     * Execute the command.
     *
     * @param array<int|string, string|null> $params
     *
     * @deprecated use generateClass() instead
     */
    protected function execute(array $params): void
    {
        $this->generate_class($params);
    }
    /**
     * Generates a class file from an existing template.
     *
     * @param array<int|string, string|null> $params
     */
    protected function generate_class(array $params): void
    {
        $this->params = $params;
        // Get the fully qualified class name from the input.
        $class = $this->qualify_class_name();
        // Get the file path from class name.
        $target = $this->build_path($class);
        // Check if path is empty.
        if ($target === '') {
            return;
        }
        $this->generate_file($target, $this->build_content($class));
    }
    /**
     * Generate a view file from an existing template.
     *
     * @param string                         $view   namespaced view name that is generated
     * @param array<int|string, string|null> $params
     */
    protected function generate_view(string $view, array $params): void
    {
        $this->params = $params;
        $target = $this->build_path($view);
        // Check if path is empty.
        if ($target === '') {
            return;
        }
        $this->generate_file($target, $this->build_content($view));
    }
    /**
     * Handles writing the file to disk, and all of the safety checks around that.
     *
     * @param string $target file path
     */
    private function generate_file(string $target, string $content): void
    {
        if ($this->get_option('namespace') === 'CodeIgniter') {
            // @codeCoverageIgnoreStart
            CLI::write(lang('CLI.generator.usingCINamespace'), 'yellow');
            CLI::new_line();
            if (CLI::prompt('Are you sure you want to continue?', ['y', 'n'], 'required') === 'n') {
                CLI::new_line();
                CLI::write(lang('CLI.generator.cancelOperation'), 'yellow');
                CLI::new_line();
                return;
            }
            CLI::new_line();
            // @codeCoverageIgnoreEnd
        }
        $is_file = is_file($target);
        // Overwriting files unknowingly is a serious annoyance, So we'll check if
        // we are duplicating things, If 'force' option is not supplied, we bail.
        if (!$this->get_option('force') && $is_file) {
            CLI::error(lang('CLI.generator.fileExist', [clean_path($target)]), 'light_gray', 'red');
            CLI::new_line();
            return;
        }
        // Check if the directory to save the file is existing.
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        helper('filesystem');
        // Build the class based on the details we have, We'll be getting our file
        // contents from the template, and then we'll do the necessary replacements.
        if (!write_file($target, $content)) {
            // @codeCoverageIgnoreStart
            CLI::error(lang('CLI.generator.fileError', [clean_path($target)]), 'light_gray', 'red');
            CLI::new_line();
            return;
            // @codeCoverageIgnoreEnd
        }
        if ($this->get_option('force') && $is_file) {
            CLI::write(lang('CLI.generator.fileOverwrite', [clean_path($target)]), 'yellow');
            CLI::new_line();
            return;
        }
        CLI::write(lang('CLI.generator.fileCreate', [clean_path($target)]), 'green');
        CLI::new_line();
    }
    /**
     * Prepare options and do the necessary replacements.
     *
     * @param string $class namespaced classname or namespaced view.
     *
     * @return string generated file content
     */
    protected function prepare(string $class): string
    {
        return $this->parse_template($class);
    }
    /**
     * Change file basename before saving.
     *
     * Useful for components where the file name has a date.
     */
    protected function basename(string $filename): string
    {
        return basename($filename);
    }
    /**
     * Parses the class name and checks if it is already qualified.
     */
    protected function qualify_class_name(): string
    {
        $class = $this->normalize_input_class_name();
        // Gets the namespace from input. Don't forget the ending backslash!
        $namespace = $this->get_namespace() . '\\';
        if (str_starts_with($class, $namespace)) {
            return $class;
            // @codeCoverageIgnore
        }
        $directory_string = $this->directory !== null ? $this->directory . '\\' : '';
        return $namespace . $directory_string . str_replace('/', '\\', $class);
    }
    private function normalize_input_class_name(): string
    {
        // Gets the class name from input.
        $class = $this->params[0] ?? CLI::get_segment(2);
        if ($class === null && $this->has_class_name) {
            $name_field = $this->class_name_lang !== '' ? $this->class_name_lang : 'CLI.generator.className.default';
            $class = CLI::prompt(lang($name_field), null, 'required');
            // Reassign the class name to the params array in case
            // the class name is requested again
            $this->params[0] = $class;
            CLI::new_line();
        }
        helper('inflector');
        $component = singular($this->component);
        /**
         * @see https://regex101.com/r/a5KNCR/2
         */
        $pattern = sprintf('/([a-z][a-z0-9_\/\\\\]+)(%s)$/i', $component);
        if (preg_match($pattern, $class, $matches) === 1) {
            $class = $matches[1] . ucfirst($matches[2]);
        }
        if ($this->enabled_suffixing && $this->get_option('suffix') && preg_match($pattern, $class) !== 1) {
            $class .= ucfirst($component);
        }
        // Trims input, normalize separators, and ensure that all paths are in Pascalcase.
        return ltrim(implode('\\', array_map(pascalize(...), explode('\\', str_replace('/', '\\', trim($class))))), '\/');
    }
    /**
     * Gets the generator view as defined in the `Config\Generators::$views`,
     * with fallback to `$template` when the defined view does not exist.
     *
     * @param array<string, mixed> $data
     */
    protected function render_template(array $data = []): string
    {
        try {
            $template = $this->template_path ?? config(Generators::class)->views[$this->name];
            return view($template, $data, ['debug' => false]);
        } catch (Throwable $e) {
            log_message('error', (string) $e);
            return view("CodeIgniter\\Commands\\Generators\\Views\\{$this->template}", $data, ['debug' => false]);
        }
    }
    /**
     * Performs pseudo-variables contained within view file.
     *
     * @param string                          $class   namespaced classname or namespaced view.
     * @param list<string>                    $search
     * @param list<string>                    $replace
     * @param array<string, bool|string|null> $data
     *
     * @return string generated file content
     */
    protected function parse_template(string $class, array $search = [], array $replace = [], array $data = []): string
    {
        // Retrieves the namespace part from the fully qualified class name.
        $namespace = trim(implode('\\', array_slice(explode('\\', $class), 0, -1)), '\\');
        $search[] = '<@php';
        $search[] = '{namespace}';
        $search[] = '{class}';
        $replace[] = '<?php';
        $replace[] = $namespace;
        $replace[] = str_replace($namespace . '\\', '', $class);
        return str_replace($search, $replace, $this->render_template($data));
    }
    /**
     * Builds the contents for class being generated, doing all
     * the replacements necessary, and alphabetically sorts the
     * imports for a given template.
     */
    protected function build_content(string $class): string
    {
        $template = $this->prepare($class);
        if ($this->sort_imports && preg_match('/(?P<imports>(?:^use [^;]+;$\n?)+)/m', $template, $match)) {
            $imports = explode("\n", trim($match['imports']));
            sort($imports);
            return str_replace(trim($match['imports']), implode("\n", $imports), $template);
        }
        return $template;
    }
    /**
     * Builds the file path from the class name.
     *
     * @param string $class namespaced classname or namespaced view.
     */
    protected function build_path(string $class): string
    {
        $namespace = $this->get_namespace();
        // Check if the namespace is actually defined and we are not just typing gibberish.
        $base = service('autoloader')->get_namespace($namespace);
        if (!$base = reset($base)) {
            CLI::error(lang('CLI.namespaceNotDefined', [$namespace]), 'light_gray', 'red');
            CLI::new_line();
            return '';
        }
        $realpath = realpath($base);
        $base = $realpath !== false ? $realpath : $base;
        $file = $base . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, trim(str_replace($namespace . '\\', '', $class), '\\')) . '.php';
        return implode(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, $file), 0, -1)) . DIRECTORY_SEPARATOR . $this->basename($file);
    }
    /**
     * Gets the namespace from the command-line option,
     * or the default namespace if the option is not set.
     * Can be overridden by directly setting $this->namespace.
     */
    protected function get_namespace(): string
    {
        return $this->namespace ?? trim(str_replace('/', '\\', $this->get_option('namespace') ?? APP_NAMESPACE), '\\');
    }
    /**
     * Allows child generators to modify the internal `$hasClassName` flag.
     *
     * @return $this
     */
    protected function set_has_class_name(bool $has_class_name)
    {
        $this->has_class_name = $has_class_name;
        return $this;
    }
    /**
     * Allows child generators to modify the internal `$sortImports` flag.
     *
     * @return $this
     */
    protected function set_sort_imports(bool $sort_imports)
    {
        $this->sort_imports = $sort_imports;
        return $this;
    }
    /**
     * Allows child generators to modify the internal `$enabledSuffixing` flag.
     *
     * @return $this
     */
    protected function set_enabled_suffixing(bool $enabled_suffixing)
    {
        $this->enabled_suffixing = $enabled_suffixing;
        return $this;
    }
    /**
     * Gets a single command-line option. Returns TRUE if the option exists,
     * but doesn't have a value, and is simply acting as a flag.
     */
    protected function get_option(string $name): bool|string|null
    {
        if (!array_key_exists($name, $this->params)) {
            return CLI::get_option($name);
        }
        return $this->params[$name] ?? true;
    }
}