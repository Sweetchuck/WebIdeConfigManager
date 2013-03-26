<?php
/**
 * @file
 * File type configuration component plugin.
 */

namespace WebIdeConfig\Plugin\Component;

use WebIdeConfig\Plugin\Component\PluginComponentBase;
use WebIdeConfig\Plugin\Component\PluginComponentInfo;
use WebIdeConfig\Manager;

$info = new PluginComponentInfo();
$info->className = __NAMESPACE__ . '\ComponentFileTypes';
$info->id = 'filetypes';
$info->label = 'File types';
$info->description = 'File type information.';
$info->directory = 'filetypes';
Manager::$pluginComponentsInfo[$info->id] = $info;

class ComponentFileTypes extends PluginComponentBase {

  /**
   * @var string
   */
  protected $pattern = '/\.xml$/';

}
