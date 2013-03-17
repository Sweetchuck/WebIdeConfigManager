<?php

/**
 * @file
 * Base class.
 */

namespace WebIdeConfig\Plugin\ConfigHome;

use WebIdeConfig\Plugin\PluginInfo;
use WebIdeConfig\Manager;

class PluginConfigHomeBase implements PluginConfigHomeInterface {
  /**
   * the collected instances.
   *
   * @var PluginConfigHomeBase[]
   */
  protected static $instances = array();

  /**
   * Get the plugin instance for the ConfigHome.
   *
   * @param PluginConfigHomeInfo $config_home_info
   *   ConfigHome information.
   *
   * @param array $options
   *   Custom options for the initializer.
   *
   * @return PluginConfigHomeInterface
   *   The ConfigHome handler plugin.
   */
  public static function factory(PluginConfigHomeInfo $config_home_info, array $options = array()) {
    if (!isset(self::$instances[$config_home_info->id])) {
      self::$instances[$config_home_info->id] = new $config_home_info->className();
      self::$instances[$config_home_info->id]->initialize($config_home_info, $options);
    }

    return self::$instances[$config_home_info->id];
  }

  /**
   * @var PluginConfigHomeInfo
   */
  protected $info = NULL;

  /**
   * @var Manager
   */
  protected $manager = NULL;

  /**
   * Get the info definition.
   *
   * @return PluginConfigHomeInfo
   *   ConfigHome plugin info definition.
   */
  public function getInfo() {
    return $this->info;
  }

  /**
   * Initialize the ConfigHome plugin.
   *
   * @param PluginInfo $info
   *   The ConfigHome information.
   * @param array $options
   *   Custom options.
   *
   * @throws \InvalidArgumentException
   */
  public function initialize(PluginInfo $info, array $options = array()) {
    if ($info instanceof PluginConfigHomeInfo == FALSE) {
      throw new \InvalidArgumentException('Info is not implements ConfigHomeInfo');
    }
    $this->info = $info;
  }
}
