#!/usr/bin/env php
<?php

/**
 * @file
 * Manage WebIde configurations files.
 *
 * Requirements: PHP >= 5.3.0
 */

namespace WebIdeConfig;

use Exception;

require_once __DIR__ . '/' . __NAMESPACE__ . '/ClassLoader.inc';
spl_autoload_register(__NAMESPACE__ . '\ClassLoader::load');

class ManagerConfigFile {
  public $pathInConfigHome;

  public $pathInWebIde;

  public function __construct($path_in_config_home, $path_in_webide) {
    $this->pathInConfigHome = $path_in_config_home;
    $this->pathInWebIde = $path_in_webide;
  }
}



/**
 * Manage template files.
 *
 * Copy files between the Git repository and WebIde configuration directory.
 *
 * @see http://youtrack.jetbrains.com/issue/IDEA-89201
 * @see http://youtrack.jetbrains.com/issue/IDEABKL-6390
 */


try {
  Manager::initialize($GLOBALS['argv']);
  Manager::execute();
  exit(Manager::getExitCode());
}
catch (Exception $e) {
  throw $e;
}
