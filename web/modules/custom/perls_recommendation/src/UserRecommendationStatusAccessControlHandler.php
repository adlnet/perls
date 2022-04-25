<?php

namespace Drupal\perls_recommendation;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the User recommendation status entity.
 *
 * @see \Drupal\perls_recommendation\Entity\UserRecommendationStatus.
 */
class UserRecommendationStatusAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\perls_recommendation\Entity\UserRecommendationStatusInterface $entity */

    switch ($operation) {

      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view unpublished user recommendation status entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'edit user recommendation status entities');

      case 'delete':

        return AccessResult::allowedIfHasPermission($account, 'delete user recommendation status entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add user recommendation status entities');
  }

}
