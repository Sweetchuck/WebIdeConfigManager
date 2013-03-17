<?php
/**
 * @file
 * Component plugin interface.
 */

namespace WebIdeConfig\Plugin\Component;

use WebIdeConfig\Plugin\Component\PluginComponentInfo;
use WebIdeConfig\Plugin\Component\ConfigFile;
use WebIdeConfig\Plugin\ConfigHome\PluginConfigHomeInfo;
use WebIdeConfig\Plugin\PluginInterface;
use WebIdeConfig\Manager;

interface PluginComponentInterface extends PluginInterface {

  /**
   * Files handled by this component.
   *
   * @return ConfigFile[]
   *   Array of handled files.
   */
  public function getFiles();

  /**
   * Get the Component instance for ConfigHome.
   *
   * @param PluginComponentInfo $component_info
   *   Component information.
   * @param PluginConfigHomeInfo $config_home_info
   *   ConfigHome information.
   *
   * @return PluginComponentInterface
   *   The Component handler plugin instance.
   */
  public static function factory(PluginComponentInfo $component_info, PluginConfigHomeInfo $config_home_info);

}
