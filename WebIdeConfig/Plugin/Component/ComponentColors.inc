<?php
/**
 * @file
 * File template configuration component plugin.
 */

namespace WebIdeConfig\Plugin\Component;

use WebIdeConfig\Plugin\Component\PluginComponentBase;
use WebIdeConfig\Plugin\Component\PluginComponentInfo;
use WebIdeConfig\Manager;

$info = new PluginComponentInfo();
$info->className = __NAMESPACE__ . '\ComponentColors';
$info->id = 'colors';
$info->label = 'Colors';
$info->description = 'Syntax highlight color and font schemes.';
$info->directory = 'colors';
Manager::$pluginComponentsInfo[$info->id] = $info;

class ComponentColors extends PluginComponentBase {

  /**
   * @var string
   */
  protected $pattern = '/\.icls$/';

}
