<?php
/**
 * @file
 * Command plugin definition. Pull the updated files.
 */

namespace WebIdeConfig\Plugin\Command;

use WebIdeConfig\Manager;

$info = new PluginCommandCopyInfo();
$info->className = __NAMESPACE__ . '\CommandPull';
$info->id = 'pull';
$info->label = 'Pull';
$info->description = 'Copy configuration files from WebIde to Git repo.';
$info->destination = 'ConfigHome';
$info->examples = array(
  'WebIdeConfigManager.php pull' => 'Copy all kind of configuration.',
  'WebIdeConfigManager.php pull templates' => 'Copy only the LiveTemplates.',
);
Manager::$pluginCommandsInfo[$info->id] = $info;

class CommandPull extends CommandCopy {}
