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
namespace Code_Igniter\Config;

use Laminas\Escaper\Escaper;
use Laminas\Escaper\Escaper_Interface;
use Laminas\Escaper\Exception\Exception_Interface;
use Laminas\Escaper\Exception\InvalidArgumentException as EscaperInvalidArgumentException;
use Laminas\Escaper\Exception\RuntimeException;
use Psr\Log\Abstract_Logger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\Logger_Aware_Interface;
use Psr\Log\Logger_Aware_Trait;
use Psr\Log\Logger_Interface;
use Psr\Log\Logger_Trait;
use Psr\Log\Log_Level;
use Psr\Log\Null_Logger;
/**
 * AUTOLOADER CONFIGURATION
 *
 * This file defines the namespaces and class maps so the Autoloader
 * can find the files as needed.
 */
class Autoload_Config
{
    /**
     * -------------------------------------------------------------------
     * Namespaces
     * -------------------------------------------------------------------
     * This maps the locations of any namespaces in your application to
     * their location on the file system. These are used by the autoloader
     * to locate files the first time they have been instantiated.
     *
     * The '/app' and '/system' directories are already mapped for you.
     * you may change the name of the 'App' namespace if you wish,
     * but this should be done prior to creating any namespaced classes,
     * else you will need to modify all of those classes for this to work.
     *
     * @var array<string, list<string>|string>
     */
    public $psr4 = [];
    /**
     * -------------------------------------------------------------------
     * Class Map
     * -------------------------------------------------------------------
     * The class map provides a map of class names and their exact
     * location on the drive. Classes loaded in this manner will have
     * slightly faster performance because they will not have to be
     * searched for within one or more directories as they would if they
     * were being autoloaded through a namespace.
     *
     * @var array<string, string>
     */
    public $classmap = [];
    /**
     * -------------------------------------------------------------------
     * Files
     * -------------------------------------------------------------------
     * The files array provides a list of paths to __non-class__ files
     * that will be autoloaded. This can be useful for bootstrap operations
     * or for loading functions.
     *
     * @var list<string>
     */
    public $files = [];
    /**
     * -------------------------------------------------------------------
     * Namespaces
     * -------------------------------------------------------------------
     * This maps the locations of any namespaces in your application to
     * their location on the file system. These are used by the autoloader
     * to locate files the first time they have been instantiated.
     *
     * Do not change the name of the CodeIgniter namespace or your application
     * will break.
     *
     * @var array<string, string>
     */
    protected $core_psr4 = ['CodeIgniter' => SYSTEMPATH, 'Config' => APPPATH . 'Config'];
    /**
     * -------------------------------------------------------------------
     * Class Map
     * -------------------------------------------------------------------
     * The class map provides a map of class names and their exact
     * location on the drive. Classes loaded in this manner will have
     * slightly faster performance because they will not have to be
     * searched for within one or more directories as they would if they
     * were being autoloaded through a namespace.
     *
     * @var array<class-string, string>
     */
    protected $core_classmap = [Abstract_Logger::class => SYSTEMPATH . 'ThirdParty/PSR/Log/AbstractLogger.php', InvalidArgumentException::class => SYSTEMPATH . 'ThirdParty/PSR/Log/InvalidArgumentException.php', Logger_Aware_Interface::class => SYSTEMPATH . 'ThirdParty/PSR/Log/LoggerAwareInterface.php', Logger_Aware_Trait::class => SYSTEMPATH . 'ThirdParty/PSR/Log/LoggerAwareTrait.php', Logger_Interface::class => SYSTEMPATH . 'ThirdParty/PSR/Log/LoggerInterface.php', Logger_Trait::class => SYSTEMPATH . 'ThirdParty/PSR/Log/LoggerTrait.php', Log_Level::class => SYSTEMPATH . 'ThirdParty/PSR/Log/LogLevel.php', Null_Logger::class => SYSTEMPATH . 'ThirdParty/PSR/Log/NullLogger.php', Exception_Interface::class => SYSTEMPATH . 'ThirdParty/Escaper/Exception/ExceptionInterface.php', Escaper_Invalid_Argument_Exception::class => SYSTEMPATH . 'ThirdParty/Escaper/Exception/InvalidArgumentException.php', RuntimeException::class => SYSTEMPATH . 'ThirdParty/Escaper/Exception/RuntimeException.php', Escaper_Interface::class => SYSTEMPATH . 'ThirdParty/Escaper/EscaperInterface.php', Escaper::class => SYSTEMPATH . 'ThirdParty/Escaper/Escaper.php'];
    /**
     * -------------------------------------------------------------------
     * Core Files
     * -------------------------------------------------------------------
     * List of files from the framework to be autoloaded early.
     *
     * @var array<int, string>
     */
    protected $core_files = [];
    /**
     * Constructor.
     *
     * Merge the built-in and developer-configured psr4 and classmap,
     * with preference to the developer ones.
     */
    public function __construct()
    {
        if (isset($_SERVER['CI_ENVIRONMENT']) && $_SERVER['CI_ENVIRONMENT'] === 'testing') {
            $this->psr4['Tests\Support'] = SUPPORTPATH;
            $this->classmap['CodeIgniter\Log\TestLogger'] = SYSTEMPATH . 'Test/TestLogger.php';
            $this->classmap['CIDatabaseTestCase'] = SYSTEMPATH . 'Test/CIDatabaseTestCase.php';
        }
        $this->psr4 = array_merge($this->core_psr4, $this->psr4);
        $this->classmap = array_merge($this->core_classmap, $this->classmap);
        $this->files = [...$this->core_files, ...$this->files];
    }
}