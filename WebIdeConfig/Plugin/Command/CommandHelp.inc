<?php
/**
 * @file
 * Pull command.
 */

namespace WebIdeConfig\Plugin\Command;

use WebIdeConfig\Manager;

$info = new PluginCommandInfo();
$info->className = __NAMESPACE__ . '\CommandHelp';
$info->id = 'help';
$info->label = 'Help';
$info->description = 'Help.';
$info->examples = array(
  'WebIdeConfigManager.php' => 'This message.',
  'WebIdeConfigManager.php help' => 'This message.',
  'WebIdeConfigManager.php help pull' => 'Help message of pull command.',
);
Manager::$pluginCommandsInfo[$info->id] = $info;

class CommandHelp extends PluginCommandBase {

  /**
   * @var string
   */
  protected $commandId = NULL;

  /**
   * Set configuration based on command line arguments.
   *
   * @param array $args
   *   Command line arguments.
   *
   * @throws \Exception
   */
  protected function initializeArguments(array $args) {
    if ($args) {
      $command_id = array_shift($args);
      if (!Manager::commandExists($command_id)) {
        throw new \Exception("Command not found: $command_id");
      }
      $this->commandId = $command_id;
    }
  }

  /**
   * Show help texts.
   */
  public function execute() {
    if ($this->commandId) {
      $this->printCommandHelp(Manager::$pluginCommandsInfo[$this->commandId], TRUE);
    }
    else {
      foreach (Manager::$pluginCommandsInfo as $command) {
        $this->printCommandHelp($command, FALSE);
      }
    }
  }

  /**
   * Print help text of a command.
   *
   * @param PluginCommandInfo $command_info
   *   Command information.
   * @param bool $with_examples
   *   Long help or not.
   */
  protected function printCommandHelp(PluginCommandInfo $command_info, $with_examples) {
    fwrite(Manager::$stdout, str_pad($command_info->id, 15, ' ', STR_PAD_RIGHT));
    fwrite(Manager::$stdout, ' ');
    fwrite(Manager::$stdout, $command_info->description . "\n");
    fwrite(Manager::$stdout, "\n");

    if ($with_examples && $command_info->examples) {
      fwrite(Manager::$stdout, "\n");
      foreach ($command_info->examples as $cmd => $description) {
        fwrite(Manager::$stdout, $cmd . "\n");
        fwrite(Manager::$stdout, str_repeat(Manager::$indent, 3) . $description . "\n");
        fwrite(Manager::$stdout, "\n");
      }
    }
  }
}
