<?php

/**
 * @file
 * ${fileDescription}
 */

namespace WebIdeConfig;

class LogEntry {
  public $level = 0;

  public $message = '';

  public $timestamp = NULL;

  public function __construct($level, $message, $timestamp = NULL) {
    $this->level = $level;
    $this->message = $message;
    $this->timestamp = $timestamp ? $timestamp : time();
  }
}
