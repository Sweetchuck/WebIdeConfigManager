<?php

namespace WebIdeConfig;

use UnexpectedValueException;
use WebIdeConfig\Plugin\Command\PluginCommandInterface;
use WebIdeConfig\Plugin\Command\PluginCommandInfo;
use WebIdeConfig\Plugin\Component\PluginComponentInterface;
use WebIdeConfig\Plugin\Component\PluginComponentInfo;
use WebIdeConfig\Plugin\ConfigHome\PluginConfigHomeInterface;
use WebIdeConfig\Plugin\ConfigHome\PluginConfigHomeInfo;

use GlobIterator;
use Exception;

class Manager {

  const OS_WINDOWS = 1;

  const OS_MAC = 2;

  const OS_LINUX = 3;

  const LOG_LEVEL_DEBUG = 1;

  const LOG_LEVEL_INFO = 2;

  const LOG_LEVEL_NOTICE = 4;

  const LOG_LEVEL_WARNING = 8;

  const LOG_LEVEL_ERR = 16;

  const LOG_LEVEL_CRIT = 32;

  const LOG_LEVEL_ALERT = 64;

  const LOG_LEVEL_EMERG = 128;

  const LOG_LEVEL_ALL = 255;

  /**
   * @var string;
   */
  public static $OS = NULL;

  /**
   * @var string;
   */
  public static $selfHome = NULL;

  /**
   * @var \WebIdeConfig\Plugin\Command\PluginCommandInfo[]
   */
  public static $pluginCommandsInfo = array();

  /**
   * @var \WebIdeConfig\Plugin\Component\PluginComponentInfo[]
   */
  public static $pluginComponentsInfo = array();

  /**
   * @var \WebIdeConfig\Plugin\ConfigHome\PluginConfigHomeInfo[]
   */
  public static $pluginConfigHomesInfo = array();

  /**
   * @var array
   */
  protected static $args = array();

  /**
   * Plugin roots.
   *
   * @var array
   */
  protected static $packageRoots = array();

  /**
   * Initialize the Manager.
   *
   * @param array $args
   *   Command line arguments.
   */
  public static function initialize($args) {
    self::$stdout = fopen('php://stdout', 'w');
    self::$stderr = fopen('php://stderr', 'w');
    self::$userHome = $_SERVER['HOME'];
    self::initializeOS();
    self::initializeSelfHome();
    self::$packageRoots[] = self::$selfHome;
    self::initializeWebIdeHome();
    self::initializeConfigHomes();
    self::initializeCommands();
    self::initializeComponents();

    $args = self::parseArguments($args);
    self::validateArguments();

    $info = self::$pluginCommandsInfo[self::$args['command_id']];
    $command_class_name = $info->className;
    self::$command = new $command_class_name();
    self::$command->initialize($info, array('args' => $args));
  }

  /**
   * Determine the operating system.
   *
   * @return int
   *   One of the self::OS_*.
   */
  protected static function initializeOS() {
    if (strpos(PHP_OS, 'Win') !== FALSE) {
      self::$OS = self::OS_WINDOWS;
    }
    elseif (strpos(PHP_OS, 'Linux') !== FALSE) {
      self::$OS = self::OS_LINUX;
    }
    else {
      self::$OS = self::OS_MAC;
    }
  }

  /**
   * Initialize command plugins.
   */
  protected static function initializeCommands() {
    foreach (self::$packageRoots as $package_root) {
      try {
        $plugins = new GlobIterator("$package_root/Plugin/Command/*.inc");
      }
      catch (UnexpectedValueException $e) {
      }
      if ($plugins) {
        while ($plugins->valid()) {
          include_once $plugins->getPathname();
          $plugins->next();
        }
      }
    }
  }

  /**
   * Initialize Component plugins.
   */
  protected static function initializeComponents() {
    foreach (self::$packageRoots as $package_root) {
      try {
        $plugins = new GlobIterator("$package_root/Plugin/Component/*.inc");
      }
      catch (UnexpectedValueException $e) {
      }
      if ($plugins) {
        while ($plugins->valid()) {
          include_once $plugins->getPathname();
          $plugins->next();
        }
      }
    }
  }

  /**
   * Initialize ConfigHome plugins.
   */
  protected static function initializeConfigHomes() {
    try {
      $config_homes = new \DirectoryIterator(self::$selfHome . '/../ConfigHome');
    }
    catch (UnexpectedValueException  $e) {
    }

    if ($config_homes) {
      while ($config_homes->valid()) {
        if ($config_homes->isDot() || !$config_homes->isDir()) {
          $config_homes->next();
          continue;
        }

        $id = $config_homes->getFilename();
        $info = new PluginConfigHomeInfo();
        $info->className = __NAMESPACE__ . '\Plugin\ConfigHome\PluginConfigHomeBase';
        $info->id = $id;
        $info->label = $id;
        $info->description = "./ConfigHome/$id";
        $info->path = realpath($config_homes->getPathname());
        self::$pluginConfigHomesInfo[$info->id] = $info;

        if (is_dir($config_homes->getPathname() . '/WebIdeConfig')) {
          self::$packageRoots[] = $config_homes->getPathname() . '/WebIdeConfig';
        }

        $config_homes->next();
      }
    }

    foreach (self::$packageRoots as $package_root) {
      try {
        $plugins = new GlobIterator("$package_root/Plugin/ConfigHome/*.inc");
      }
      catch (UnexpectedValueException $e) {
      }

      if ($plugins) {
        while ($plugins->valid()) {
          include_once $plugins->getPathname();
          $plugins->next();
        }
      }
    }
  }

  /**
   * Check the command is exists.
   *
   * @param string $command_id
   *   Command ID.
   *
   * @return bool
   *   Exists ot not.
   */
  public static function commandExists($command_id) {
    return isset(self::$pluginCommandsInfo[$command_id]);
  }

  /**
   * Check the $component_id is exists.
   *
   * @param string $component_id
   *   ConfigHome directory name.
   *
   * @return bool
   *   Exists ot not.
   */
  public static function componentExists($component_id) {
    return isset(self::$pluginComponentsInfo[$component_id]);
  }

  /**
   * Check the $config_home_id is exists.
   *
   * @param string $config_home_id
   *   ConfigHome directory name.
   *
   * @return bool
   *   Exists ot not.
   */
  public static function configHomeExists($config_home_id) {
    return isset(self::$pluginConfigHomesInfo[$config_home_id]);
  }

  /**
   * Standard output.
   *
   * @var resource
   */
  public static $stdout = NULL;

  /**
   * Standard error output.
   *
   * @var resource
   */
  public static $stderr = NULL;

  /**
   * Indent characters.
   *
   * @var string
   */
  public static $indent = '  ';

  /**
   * WebIde configuration home.
   *
   * @var string
   */
  public static $webIdeHome = NULL;

  /**
   * Config directory under WebIde configuration home.
   *
   * This handle the differences between Mac and Linux.
   *
   * @var string
   */
  public static $webIdeHomeConfig = NULL;

  /**
   * Filter log entries.
   *
   * @var int
   */
  protected static $logLevel = self::LOG_LEVEL_ALL;

  /**
   * Minimum major version of the PhpStorm.
   *
   * @var integer
   */
  protected static $webIdeVersionMin = '40';

  /**
   * Maximum major version of the PhpStorm.
   *
   * @var integer
   */
  protected static $webIdeVersionMax = '60';

  /**
   * Commandline exit code.
   *
   * @var integer
   */
  protected static $exitCode = 0;

  /**
   * Current user's home directory.
   *
   * @var string
   */
  protected static $userHome = NULL;

  /**
   * Pattern for WebIde configuration home.
   *
   * The keys are WebIdeTemplateManager::OS_* constants.
   *
   * @var string[]
   */
  protected static $webIdeHomeGlobs = array(
    // Win.
    1 => '.WebIde*',
    // Mac.
    2 => 'Library/Preferences/WebIde*',
    // Linux.
    3 => '.WebIde*',
  );

  /**
   * @var \WebIdeConfig\Plugin\Command\PluginCommandInterface
   */
  protected static $command = NULL;

  /**
   * @var \WebIdeConfig\Plugin\ConfigHome\PluginConfigHomeInterface[]
   */
  protected static $pluginConfigHomeInstances = array();

  /**
   * Set the base directory. (Script home).
   */
  protected static function initializeSelfHome() {
    self::$selfHome = __DIR__;
  }

  /**
   * Set the target .WebIde## directory.
   *
   * @throws \Exception
   */
  protected static function initializeWebIdeHome() {
    self::$webIdeHome = self::$userHome;
    $webide_homes = self::webIdeHomes();
    if (!$webide_homes) {
      throw new Exception('.WebIde## not found');
    }
    // Get the latest.
    self::$webIdeHome = array_pop($webide_homes);

    self::$webIdeHomeConfig = self::$webIdeHome;
    if (self::$OS == self::OS_LINUX) {
      self::$webIdeHomeConfig .= '/config';
    }
  }

  /**
   * Find all WebIde## directory in user's home.
   *
   * @return string[]
   *   Directories.
   *
   * @throws \Exception
   */
  protected static function webIdeHomes() {
    try {
      $webide_home = new GlobIterator(self::$userHome . '/' . self::$webIdeHomeGlobs[self::$OS]);
    }
    catch (UnexpectedValueException $e) {
      throw new Exception('.WebIde not found', 1, $e);
    }

    while ($webide_home->valid()) {
      if (
        $webide_home->isDir()
        &&
        ($version = self::getWebIdeVersionFromPath($webide_home->getPathname()))
        &&
        (
          $version >= self::$webIdeVersionMin
          &&
          (!self::$webIdeVersionMax || $version <= self::$webIdeVersionMax)
        )
      ) {
        $return[] = $webide_home->getPathname();
      }

      $webide_home->next();
    }
    sort($return);

    return $return;
  }

  /**
   * Parse the version number from WebIde directory name.
   *
   * @param string $path
   *   String with trailing decimal characters.
   *
   * @return string
   *   Version number.
   */
  protected static function getWebIdeVersionFromPath($path) {
    $matches = NULL;

    return (preg_match('/\d+$/', $path, $matches)) ? $matches[0] : 0;
  }

  /**
   * Parse command line arguments.
   *
   * @param array $args
   *   Command line arguments.
   *
   * @return array
   *   Remaining arguments.
   */
  protected static function parseArguments(array $args) {
    // Drop the script name.
    array_shift($args);

    self::$args['command_id'] = array_shift($args);

    if (empty(self::$args['command_id']) && self::commandExists('help')) {
      self::$args['command_id'] = 'help';
    }

    return $args;
  }

  /**
   * Basic validation of the command line arguments.
   *
   * Find the command.
   *
   * @throws \Exception
   */
  protected static function validateArguments() {
    if (empty(self::$args['command_id'])) {
      throw new Exception('Too few arguments');
    }

    if (!self::commandExists(self::$args['command_id'])) {
      throw new Exception('Unknown command: ' . self::$args['command_id']);
    }
  }

  /**
   * Get the Component handler plugin.
   *
   * @param string $config_home_id
   *   ConfigHome identifier.
   *
   * @param string $component_id
   *   Component identifier.
   *
   * @return PluginComponentInterface
   *   The component handler plugin.
   *
   * @throws \InvalidArgumentException
   */
  public static function getPluginComponentInstance($config_home_id, $component_id) {
    if (!isset(self::$pluginConfigHomesInfo[$config_home_id])) {
      throw new \InvalidArgumentException();
    }

    if (!isset(self::$pluginComponentsInfo[$component_id])) {
      throw new \InvalidArgumentException();
    }

    $component_info = self::$pluginComponentsInfo[$component_id];
    $class_name = $component_info->className;

    $config_home_info = self::$pluginConfigHomesInfo[$config_home_id];

    return $class_name::factory($component_info, $config_home_info);
  }

  /**
   * Get the ConfigHome handler plugin.
   *
   * @param string $config_home_id
   *   ConfigHome identifier.
   *
   * @return \WebIdeConfig\Plugin\ConfigHome\PluginConfigHomeInterface
   *   The ConfigHome handler plugin.
   *
   * @throws \InvalidArgumentException
   */
  public static function getPluginConfigHomeInstance($config_home_id) {
    if (!isset(self::$pluginConfigHomesInfo[$config_home_id])) {
      throw new \InvalidArgumentException();
    }

    $config_home_info = self::$pluginConfigHomesInfo[$config_home_id];
    $class_name = $config_home_info->className;

    return $class_name::factory($config_home_info);
  }

  /**
   * Run the command.
   */
  public static function execute() {
    self::$command->execute();
  }

  /**
   * Get the current log level.
   *
   * @return int
   *   The current log level.
   */
  public static function getLogLevel() {
    return self::$logLevel;
  }

  /**
   * Update the current log level.
   *
   * @param int $log_level
   *   The new log level.
   *
   * @throws \Exception
   */
  public static function setLogLevel($log_level) {
    settype($log_level, 'integer');
    if ($log_level > self::LOG_LEVEL_ALL) {
      throw new Exception('Log level is out of range');
    }

    self::$logLevel = $log_level;
  }

  /**
   * Write the log to standard error output.
   *
   * @param LogEntry $log
   *   Log entry information.
   */
  public static function writeLog(LogEntry $log) {
    if ((self::$logLevel & $log->level) != self::$logLevel) {
      return;
    }

    fwrite(self::$stderr, '[' . self::logLevelLabel($log->level) . '] ' . $log->message);
  }

  /**
   * Convert log level value to human readable.
   *
   * @param int $log_level
   *   Log level value.
   *
   * @return string
   *   Human readable log level name.
   */
  public static function logLevelLabel($log_level) {
    if ((self::LOG_LEVEL_EMERG & $log_level) == self::LOG_LEVEL_EMERG) {
      return 'EMERGENCY';
    }
    if ((self::LOG_LEVEL_ALERT & $log_level) == self::LOG_LEVEL_ALERT) {
      return 'ALERT';
    }
    if ((self::LOG_LEVEL_CRIT & $log_level) == self::LOG_LEVEL_CRIT) {
      return 'CRITICAL';
    }
    if ((self::LOG_LEVEL_ERR & $log_level) == self::LOG_LEVEL_ERR) {
      return 'ERROR';
    }
    if ((self::LOG_LEVEL_WARNING & $log_level) == self::LOG_LEVEL_WARNING) {
      return 'WARNING';
    }
    if ((self::LOG_LEVEL_NOTICE & $log_level) == self::LOG_LEVEL_NOTICE) {
      return 'NOTICE';
    }
    if ((self::LOG_LEVEL_INFO & $log_level) == self::LOG_LEVEL_INFO) {
      return 'INFO';
    }
    if ((self::LOG_LEVEL_DEBUG & $log_level) == self::LOG_LEVEL_DEBUG) {
      return 'DEBUG';
    }

    return 'UNKNOWN';
  }

  /**
   * Close the resources.
   */
  public function __destruct() {
    if (is_resource(self::$stderr)) {
      fclose(self::$stderr);
    }

    if (is_resource(self::$stdout)) {
      fclose(self::$stdout);
    }
  }

  /**
   * Recommended exit code.
   *
   * @return int
   *   The exit code number.
   */
  public static function getExitCode() {
    return self::$exitCode;
  }

  /**
   * Set the recommended exit code.
   *
   * @param int $exit_code
   *   The recommended exit code.
   */
  protected static function setExitCode($exit_code) {
    if (!self::$exitCode) {
      self::$exitCode = $exit_code;
    }
  }
}
