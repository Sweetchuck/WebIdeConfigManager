<?php
/**
 * @file
 * LiveTemplate configuration component plugin.
 */

namespace WebIdeConfig\Plugin\Component;

use WebIdeConfig\Plugin\Component\PluginComponentBase;
use WebIdeConfig\Plugin\Component\PluginComponentInfo;
use WebIdeConfig\Manager;

$info = new PluginComponentInfo();
$info->className = __NAMESPACE__ . '\ComponentTemplates';
$info->id = 'templates';
$info->label = 'LiveTemplates';
$info->description = 'LiveTemplates.';
$info->directory = 'templates';
Manager::$pluginComponentsInfo[$info->id] = $info;

class ComponentTemplates extends PluginComponentBase {}
