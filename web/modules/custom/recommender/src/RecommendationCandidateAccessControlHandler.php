<?php

namespace Drupal\recommender;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the User recommendation status entity.
 *
 * @see \Drupal\recommender\Entity\RecommendationCandidate.
 */
class RecommendationCandidateAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {

      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view recommendation candidate entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'edit recommendation candidates entities');

      case 'delete':

        return AccessResult::allowedIfHasPermission($account, 'delete recommendation candidate entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add recommendation candidate entities');
  }

}
