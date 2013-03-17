<?php
/**
 * @file
 * ${fileDescription}
 */

namespace WebIdeConfig\Plugin;

use \WebIdeConfig\Manager;

interface PluginInterface {

  /**
   * Plugin initializer.
   *
   * @param PluginInfo $info
   *   Plugin information.
   * @param array $options
   *   Custom options to initializer.
   */
  public function initialize(PluginInfo $info, array $options = array());

  /**
   * Get the plugin definition.
   *
   * @return PluginInfo
   *   The plugin definition.
   */
  public function getInfo();
}
