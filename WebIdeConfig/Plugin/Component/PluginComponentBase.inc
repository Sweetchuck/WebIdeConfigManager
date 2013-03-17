<?php

/**
 * @file
 * ${fileDescription}
 */

namespace WebIdeConfig\Plugin\Component;

use WebIdeConfig\Plugin\Component\PluginComponentInterface;
use WebIdeConfig\Plugin\Component\PluginComponentInfo;
use WebIdeConfig\Plugin\ConfigHome\PluginConfigHomeInterface;
use WebIdeConfig\Plugin\ConfigHome\PluginConfigHomeInfo;
use WebIdeConfig\Plugin\PluginInfo;
use WebIdeConfig\Manager;

abstract class PluginComponentBase implements PluginComponentInterface {
  /**
   * Component plugin instances.
   *
   * First level key is $config_home_id, the values are array of component
   * keyed by component_id.
   *
   * @var PluginComponentBase[]
   */
  protected static $instances = array();

  /**
   * @param PluginComponentInfo $component_info
   * @param PluginConfigHomeInfo $config_home_info
   *
   * @return PluginComponentBase
   * @throws \InvalidArgumentException
   */
  public static function factory(PluginComponentInfo $component_info, PluginConfigHomeInfo $config_home_info, array $options = array()) {
    if (!isset(self::$instances[$config_home_info->id][$component_info->id])) {
      self::$instances[$config_home_info->id][$component_info->id] = new $component_info->className();
      $options['configHome'] = Manager::getPluginConfigHomeInstance($config_home_info->id);
      self::$instances[$config_home_info->id][$component_info->id]->initialize($component_info, $options);
    }

    return self::$instances[$config_home_info->id][$component_info->id];
  }

  /**
   * @var PluginComponentInfo
   */
  protected $info = NULL;

  /**
   * @var Manager
   */
  protected $manager = NULL;

  /**
   * @var \WebIdeConfig\Plugin\ConfigHome\PluginConfigHomeInterface
   */
  public $configHome = NULL;

  /**
   * @var string
   */
  protected $glob = '*.*';

  /**
   * Get the info definition.
   *
   * @return PluginComponentInfo
   *   Command plugin info definition.
   */
  public function getInfo() {
    return $this->info;
  }

  /**
   * Initialize the plugin instance.
   *
   * @param PluginInfo $info
   *   Component plugin infoarmation.
   * @param array $options
   *   Custom options.
   *
   * @throws \InvalidArgumentException
   */
  public function initialize(PluginInfo $info, array $options = array()) {
    if ($info instanceof PluginComponentInfo == FALSE) {
      throw new \InvalidArgumentException('Info is not implements ComponentInfo');
    }

    $this->info = $info;
    // @todo Check configHome.
    $this->configHome = $options['configHome'];
  }

  /**
   * Array of configuration files.
   *
   * @return ConfigFile[]
   *   Configuration files in this scope.
   */
  public function getFiles() {
    $return = array();

    $config_home_path = $this->configHome->getInfo()->path;
    $files = new \GlobIterator("{$config_home_path}/{$this->info->directory}/{$this->glob}");
    while ($files->valid()) {
      $config_file = new ConfigFile();
      $config_file->relativePath = $this->relativePath($files->getPathname());
      $config_file->fullPathConfigHome = $files->getPathname();
      $config_file->fullPathWebIde = Manager::$webIdeHomeConfig . '/' . $config_file->relativePath;
      $return[$config_file->relativePath] = $config_file;
      $files->next();
    }

    return $return;
  }

  /**
   * Get the relative path from ConfigHome full path.
   *
   * @param string $full_path
   *   ConfigHome full path.
   *
   * @return string
   *   Relative path.
   */
  protected function relativePath($full_path) {
    return substr($full_path, strlen($this->configHome->getInfo()->path) + 1);
  }
}
