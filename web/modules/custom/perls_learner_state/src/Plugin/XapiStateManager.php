<?php

namespace Drupal\perls_learner_state\Plugin;

use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the xapi state plugin manager.
 */
class XapiStateManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/XapiState', $namespaces, $module_handler, NULL, 'Drupal\perls_learner_state\Annotation\XapiState');
  }

  /**
   * Sends an xAPI statement.
   *
   * @param string $pluginId
   *   The ID of the template (plugin) for the statement.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The object of the statement.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   The actor of the statement.
   * @param int|null $timestamp
   *   A timestamp for the statement.
   */
  public function sendStatement(string $pluginId, ?EntityInterface $entity = NULL, ?AccountInterface $user = NULL, ?int $timestamp = NULL) {
    if ($user !== NULL && !($user instanceof UserInterface)) {
      $user = User::load($user->id());
    }

    $plugin = $this->createInstance($pluginId);
    $plugin->getReadyStatement($entity, $timestamp, $user);
    $plugin->sendStatement();
  }

}
