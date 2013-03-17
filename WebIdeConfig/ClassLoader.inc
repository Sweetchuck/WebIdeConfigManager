<?php
/**
 * @file
 * Class auto loader.
 */

namespace WebIdeConfig;

/**
 * Auto loader.
 *
 * @package WebIdeConfig
 */
class ClassLoader {
  /**
   * @var string[]
   */
  protected static $roots = array();

  /**
   * Find the file.
   *
   * @param string $identifier
   *   Class or interface identifier.
   */
  public static function load($identifier) {
    if (empty(self::$roots)) {
      self::initializeRoots();
    }

    $identifier = str_replace('\\', '/', $identifier);
    foreach (self::$roots as $root) {
      if (file_exists("$root/$identifier.inc")) {
        include_once "$root/$identifier.inc";
        break;
      }
      elseif (file_exists("$root/$identifier.php")) {
        include_once "$root/$identifier.php";
        break;
      }
    }
  }

  /**
   * Find package roots.
   */
  protected static function initializeRoots() {
    self::$roots[] = realpath(__DIR__ . '/..');
    $config_homes = new \DirectoryIterator(__DIR__ . '/../ConfigHome');
    while ($config_homes->valid()) {
      if (
        !$config_homes->isDot()
        && $config_homes->isDir()
        && is_dir($config_homes->getPathname() . '/' . __NAMESPACE__)
      ) {
        self::$roots = realpath($config_homes->getPathname());
      }

      $config_homes->next();
    }
  }
}
