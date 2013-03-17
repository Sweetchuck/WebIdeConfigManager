<?php
/**
 * @file
 * ${fileDescription}
 */

namespace WebIdeConfig\Plugin\ConfigHome;

interface PluginConfigHomeInterface extends \WebIdeConfig\Plugin\PluginInterface {

  /**
   * Only one instance per ConfigHome.
   *
   * @param PluginConfigHomeInfo $config_home_info
   *   The ConfigHome information strucure.
   *
   * @return PluginConfigHomeInterface
   *   The plugin handler object.
   */
  public static function factory(PluginConfigHomeInfo $config_home_info);
}
