<?php

/**
 * @file
 * Base classes
 */

namespace WebIdeConfig\Plugin\Command;

use WebIdeConfig\Plugin\Command\PluginCommandInterface;
use WebIdeConfig\Plugin\Command\PluginCommandInfo;
use WebIdeConfig\Manager;

abstract class PluginCommandBase implements PluginCommandInterface {
  /**
   * @var PluginCommandInfo
   */
  protected $info = NULL;

  /**
   * Get the info definition.
   *
   * @return PluginCommandInfo
   *   Command plugin info definition.
   */
  public function getInfo() {
    return $this->info;
  }

  /**
   * Initialize the plugin.
   *
   * @param \WebIdeConfig\Plugin\PluginInfo $info
   *   Plugin information.
   * @param array $options
   *   Custom options ti initializer.
   *
   * @throws \InvalidArgumentException
   */
  public function initialize(\WebIdeConfig\Plugin\PluginInfo $info, array $options = array()) {
    if ($info instanceof PluginCommandInfo == FALSE) {
      throw new \InvalidArgumentException('Info is not implements CommandInfo');
    }
    $this->info = $info;
    $this->initializeArguments($options['args']);
  }

  /**
   * Parse the command line arguments.
   *
   * @param array $args
   *   Command line arguments
   */
  abstract protected function initializeArguments(array $args);

  /**
   * Run the command.
   */
  abstract public function execute();
}
