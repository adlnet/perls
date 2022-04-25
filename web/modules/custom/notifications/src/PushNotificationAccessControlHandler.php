<?php

namespace Drupal\notifications;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Access\AccessResult;
use Drupal\notifications\Entity\PushNotification;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access controller for the Push Notification Token entity.
 *
 * @see \Drupal\notifications\Entity\PushNotification.
 */
class PushNotificationAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {
  /**
   * Contains the configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a TranslatorAccessControlHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config object factory.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory) {
    parent::__construct($entity_type);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (in_array($operation, ['update', 'cancel']) && $entity->getSendStatus() !== PushNotification::PUSH_NOTIFICATION_PENDING) {
      return AccessResult::forbidden()
        ->addCacheableDependency($entity);
    }

    if ($operation === 'clone') {
      return parent::checkAccess($entity, 'update', $account);
    }

    if ($account->hasPermission('administer push notifications')) {
      return AccessResult::allowed()->addCacheContexts(['user.permissions']);
    }

    if (in_array($operation, ['view', 'update', 'cancel', 'sendnow'])) {
      return AccessResult::allowedIfHasPermission($account, 'send push notifications');
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
