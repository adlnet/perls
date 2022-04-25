<?php

namespace Drupal\notifications;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of Access Token entities.
 *
 * @ingroup notifications
 */
class PushNotificationTokenListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['user'] = $this->t('User');
    $header['name'] = $this->t('Token');
    $header['device'] = $this->t('Device');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\notifications\Entity\PushNotificationToken $entity */
    $row['id'] = $entity->id();
    $row['user'] = NULL;
    $row['name'] = $entity->toLink(sprintf('%s…', substr($entity->label(), 0, 10)));
    $row['device'] = NULL;
    if (($user = $entity->get('auth_user_id')) && $user->entity) {
      $row['user'] = $user->entity->toLink($user->entity->label());
    }
    if (($device = $entity->get('device'))) {
      $row['device'] = sprintf('%s…', substr($device->getString(), 0, 10));
    }
    return $row + parent::buildRow($entity);
  }

}
