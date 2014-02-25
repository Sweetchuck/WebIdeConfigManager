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
$info->className = __NAMESPACE__ . '\ComponentFileTemplates';
$info->id = 'fileTemplates';
$info->label = 'File templates';
$info->description = 'File templates.';
$info->directory = 'fileTemplates';
Manager::$pluginComponentsInfo[$info->id] = $info;

class ComponentFileTemplates extends PluginComponentBase {
  protected $fileScanOptions = array('recurse' => TRUE);

  /**
   * {@inheritdoc}
   */
  protected $pattern = '/.*/';
}
