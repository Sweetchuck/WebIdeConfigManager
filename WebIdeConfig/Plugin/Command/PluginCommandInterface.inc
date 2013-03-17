<?php
/**
 * @file
 * ${fileDescription}
 */

namespace WebIdeConfig\Plugin\Command;

use WebIdeConfig\Plugin\PluginInterface;

interface PluginCommandInterface extends PluginInterface {

  public function execute();

}
