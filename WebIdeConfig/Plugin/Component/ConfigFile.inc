<?php
/**
 * @file
 * Configuration file.
 */

namespace WebIdeConfig\Plugin\Component;

class ConfigFile {

  const COPY_UPDATE = 1;
  const COPY_NEW = 2;
  const COPY_SKIP = 3;

  /**
   * Relative file path from ConfigHome.
   *
   * @var string
   */
  public $relativePath = '';

  /**
   * Full path to WebIde instance.
   *
   * @var string
   */
  public $fullPathWebIde = '';

  /**
   * Full path to ConfigHome instance.
   *
   * @var string
   */
  public $fullPathConfigHome = '';

  /**
   * Cache.
   *
   * @var bool
   */
  protected $existsInWebIde = NULL;

  /**
   * Cache.
   *
   * @var bool
   */
  protected $existsInConfigHome = NULL;

  /**
   * The files are the same.
   *
   * @var bool
   */
  protected $identical = NULL;

  /**
   * Configuration file is exist in .WebIde or not.
   *
   * @return bool
   *   Exist or not.
   */
  public function existsInWebIde() {
    if ($this->existsInWebIde === NULL) {
      $this->existsInWebIde = file_exists($this->fullPathWebIde);
    }

    return $this->existsInWebIde;
  }

  /**
   * Configuration file is exist in ConfigHome or not.
   *
   * @return bool
   *   Exists or not.
   */
  public function existsInConfigHome() {
    if ($this->existsInConfigHome === NULL) {
      $this->existsInConfigHome = file_exists($this->fullPathConfigHome);
    }

    return $this->existsInConfigHome;
  }

  /**
   * The files are the same or not.
   *
   * @return bool
   *   The result of the comparison. TRUE if the files are the same.
   */
  public function identical() {
    if ($this->identical === NULL) {
      if ($this->existsInWebIde() XOR $this->existsInConfigHome()) {
        $this->identical = FALSE;
      }
      else {
        $this->identical = (file_get_contents($this->fullPathWebIde) == file_get_contents($this->fullPathConfigHome));
      }
    }

    return $this->identical;
  }

  /**
   * Copy this file to ConfigHome.
   *
   * @return int
   *   ConfigFile::COPY_*
   *
   * @throws \Exception
   */
  public function copyToConfigHome() {
    if (!$this->existsInConfigHome()) {
      throw new \Exception();
    }

    if ($this->identical() || !$this->existsInWebIde()) {
      return self::COPY_SKIP;
    }

    if ($this->existsInConfigHome()) {
      file_put_contents($this->fullPathConfigHome, file_get_contents($this->fullPathWebIde));
      $return = self::COPY_UPDATE;
    }
    else {
      $this->prepareDirectory(dirname($this->fullPathWebIde));
      copy($this->fullPathWebIde, $this->fullPathConfigHome);
      $return = self::COPY_NEW;
    }

    $this->resetStatus();

    return $return;
  }


  /**
   * Copy this file to WebIde.
   *
   * @return int
   *   ConfigFile::COPY_*
   *
   * @throws \Exception
   */
  public function copyToWebIde() {
    if (!$this->existsInConfigHome()) {
      throw new \Exception();
    }

    if ($this->identical()) {
      return self::COPY_SKIP;
    }

    $return = NULL;

    if ($this->existsInWebIde()) {
      file_put_contents($this->fullPathWebIde, file_get_contents($this->fullPathConfigHome));
      $return = self::COPY_UPDATE;
    }
    else {
      $this->prepareDirectory(dirname($this->fullPathWebIde));
      copy($this->fullPathConfigHome, $this->fullPathWebIde);
      $return = self::COPY_NEW;
    }

    $this->resetStatus();

    return $return;
  }

  /**
   * Prepare the destination directory.
   *
   * @param string $dir
   *   Directory to create if not exists.
   */
  protected function prepareDirectory($dir) {
    if (!file_exists($dir)) {
      mkdir($dir, 0777 - umask(), TRUE);
    }
  }

  /**
   * Clear the cache.
   */
  public function resetStatus() {
    $this->existsInConfigHome = NULL;
    $this->existsInWebIde = NULL;
    $this->identical = NULL;
  }
}
