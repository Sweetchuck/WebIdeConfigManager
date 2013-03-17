<?php
/**
 * @file
 * Basic plugin info structure.
 */

namespace WebIdeConfig\Plugin;

use \WebIdeConfig\Structure;

class PluginInfo extends Structure {

  /**
   * Required.
   *
   * @var \WebIdeConfig\Plugin\PluginInterface
   */
  public $className = NULL;

  /**
   * Required.
   *
   * @var string
   */
  public $id = '';

  /**
   * Required.
   *
   * @var string
   */
  public $label = '';

  /**
   * Optional.
   *
   * @var string
   */
  public $description = '';
}
