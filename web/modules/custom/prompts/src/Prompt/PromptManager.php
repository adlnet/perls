<?php

namespace Drupal\prompts\Prompt;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages prompt plugin types.
 *
 * @package Drupal\prompts
 */
class PromptManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Prompt', $namespaces, $module_handler, 'Drupal\prompts\Prompt\PromptTypeInterface', 'Drupal\prompts\Annotation\Prompt');
  }

}
