<?php

/**
 * @file
 * Push and Pull commands.
 */

namespace WebIdeConfig\Plugin\Command;

use WebIdeConfig\Manager;

$info = new PluginCommandCopyInfo();
$info->className = __NAMESPACE__ . '\CommandPush';
$info->id = 'push';
$info->label = 'Push';
$info->description = 'Copy configuration files from Git repo to WebIde.';
$info->destination = 'WebIde';
$info->examples = array(
  'WebIdeConfigManager.php push' => 'Copy all kind of configuration.',
  'WebIdeConfigManager.php push templates' => 'Copy only the LiveTemplates.',
);
Manager::$pluginCommandsInfo[$info->id] = $info;

class CommandPush extends CommandCopy {}
