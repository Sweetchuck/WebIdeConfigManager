<?php
/**
 * @file
 * ${fileDescription}
 */

namespace WebIdeConfig\Plugin\Command;

use WebIdeConfig\Plugin\PluginInfo;

class PluginCommandInfo extends PluginInfo {

  /**
   * @var PluginCommandInterface
   */
  public $className = NULL;

  /**
   * @var string[]
   */
  public $examples = array();

}
