<?php
/**
 * @file
 * Base class for Push and Pull commands.
 */

namespace WebIdeConfig\Plugin\Command;

use WebIdeConfig\Plugin\Component\ConfigFile;
use WebIdeConfig\Plugin\ConfigHome\PluginConfigHomeInterface;
use WebIdeConfig\Manager;

abstract class CommandCopy extends PluginCommandBase {
  /**
   * @var PluginCommandCopyInfo
   */
  protected $info = NULL;

  /**
   * Affected ConfigHome identifiers in the file transmission.
   *
   * @var array
   */
  protected $configHomeIds = array();

  /**
   * Affected Component identifiers in the file transmission.
   *
   * @var array
   */
  protected $componentIds = array();

  /**
   * Parse the command line arguments.
   *
   * @param array $args
   *   The command line arguments.
   */
  public function initializeArguments(array $args) {
    if (empty($args)) {
      $this->configHomeIds = array_keys(Manager::$pluginConfigHomesInfo);
      $this->componentIds = array_keys(Manager::$pluginComponentsInfo);

      return;
    }

    $this->initializeArgumentsConfigHomes($args);
    if (empty($this->configHomeIds)) {
      $this->configHomeIds = array_keys(Manager::$pluginConfigHomesInfo);
    }

    $this->initializeArgumentsComponents($args);
    if (empty($this->componentIds)) {
      $this->componentIds = array_keys(Manager::$pluginComponentsInfo);
    }
  }

  /**
   * Retrieve the ConfigHome identifiers from the command line arguments.
   *
   * @param array $args
   *   Command line arguments.
   *
   * @throws \ErrorException
   * @throws \Exception
   */
  protected function initializeArgumentsConfigHomes(array &$args) {
    if ($args[0] != '-h') {
      return;
    }
    array_shift($args);

    if (!$args) {
      throw new \ErrorException('Invalid command line arguments.');
    }

    while ($args) {
      if ($args[0][0] == '-') {
        break;
      }

      if (!Manager::configHomeExists($args[0])) {
        throw new \Exception('Config home does not exists: ' . $args[0]);
      }
      else {
        $this->configHomeIds[] = array_shift($args);
      }
    }

    if (empty($this->configHomeIds)) {
      throw new \Exception('Specify least one config home or omit the -h option.');
    }
  }

  /**
   * Retrieve the Component identifiers from the command line arguments.
   *
   * @param array $args
   *   Command line arguments.
   *
   * @throws \ErrorException
   * @throws \Exception
   */
  protected function initializeArgumentsComponents(&$args) {
    if (!$args || $args[0] != '-c') {
      return;
    }
    array_shift($args);

    if (!$args) {
      throw new \ErrorException('Invalid command line arguments.');
    }

    while ($args) {
      if ($args[0][0] == '-') {
        break;
      }

      if (!Manager::componentExists($args[0])) {
        throw new \Exception('Invalid component name: ' . $args[0]);
      }
      else {
        $this->componentIds[] = array_shift($args);
      }
    }

    if (empty($this->componentIds)) {
      throw new \Exception('Specify least one component name or omit the -c option.');
    }
  }

  /**
   * Run the command.
   *
   * Start the file transmissions.
   */
  public function execute() {
    fwrite(Manager::$stdout, 'WebIde configuration directory is ' . Manager::$webIdeHome . "\n");
    foreach ($this->configHomeIds as $config_home_id) {
      $config_home = Manager::getPluginConfigHomeInstance($config_home_id);
      foreach ($this->componentIds as $component_id) {
        switch ($component_id) {
          case 'fileTemplates':
            $this->executeFileTemplates($config_home);
            break;

          case 'filetypes':
            $this->executeFileTypes($config_home);
            break;

          case 'templates':
            $this->executeLiveTemplates($config_home);
            break;

        }
      }
    }
  }

  /**
   * Process the fileTemplates.
   *
   * @param PluginConfigHomeInterface $config_home
   *   Affected ConfigHome.
   */
  protected function executeFileTemplates(PluginConfigHomeInterface $config_home) {
    $component = Manager::getPluginComponentInstance($config_home->getInfo()->id, 'fileTemplates');
    fwrite(Manager::$stdout, Manager::$indent . $component->getInfo()->label . "\n");
    $this->copyFiles($component->getFiles(), $this->info->destination);
  }

  /**
   * Process the filetypes.
   *
   * @param PluginConfigHomeInterface $config_home
   *   Affected ConfigHome.
   */
  protected function executeFileTypes(PluginConfigHomeInterface $config_home) {
    $component = Manager::getPluginComponentInstance($config_home->getInfo()->id, 'filetypes');
    fwrite(Manager::$stdout, Manager::$indent . $component->getInfo()->label . "\n");
    $this->copyFiles($component->getFiles(), $this->info->destination);
  }

  /**
   * Process the templates.
   *
   * @param PluginConfigHomeInterface $config_home
   *   Affected ConfigHome.
   */
  protected function executeLiveTemplates(PluginConfigHomeInterface $config_home) {
    $component = Manager::getPluginComponentInstance($config_home->getInfo()->id, 'templates');
    fwrite(Manager::$stdout, Manager::$indent . $component->getInfo()->label . "\n");
    $this->copyFiles($component->getFiles(), $this->info->destination);
  }

  /**
   * Helper function to deploy symbolic links.
   *
   * @param ConfigFile[] $config_files
   *   Files.
   * @param string $destination
   *   Destination.
   *
   * @throws \UnexpectedValueException
   */
  protected function copyFiles(array $config_files, $destination) {
    if (!in_array($destination, array('WebIde', 'ConfigHome'))) {
      throw new \UnexpectedValueException("\$destination = $destination");
    }

    foreach ($config_files as $config_file) {
      $result = $destination == 'WebIde' ?
        $config_file->copyToWebIde() : $config_file->copyToConfigHome();

      $status = ' ';
      if ($result === ConfigFile::COPY_NEW) {
        $status = 'NEW    ';
      }
      elseif ($result == ConfigFile::COPY_UPDATE) {
        $status = 'UPDATE ';

      }
      elseif ($result == ConfigFile::COPY_SKIP) {
        $status = 'SKIP   ';

      }
      fwrite(Manager::$stdout, str_repeat(Manager::$indent, 2) . $status . $config_file->relativePath . "\n");
    }
  }
}
