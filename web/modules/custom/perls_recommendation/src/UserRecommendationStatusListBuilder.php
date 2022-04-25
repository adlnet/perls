<?php

namespace Drupal\perls_recommendation;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of User recommendation status entities.
 *
 * @ingroup perls_recommendation
 */
class UserRecommendationStatusListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('User recommendation status ID');
    $header['user'] = $this->t('User');
    $header['status'] = $this->t('Current Status');
    $header['recommendations_updated'] = $this->t('Last Updated');
    $header['recommendations_retrieved'] = $this->t('Number Recommendations Retrieved');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\perls_recommendation\Entity\UserRecommendationStatus $entity */
    $row['id'] = $entity->id();
    $row['user'] = NULL;
    $row['status'] = $entity->getStatus();
    $row['recommendations_updated'] = $entity->getUpdated();
    $row['recommendations_retrieved'] = $entity->getRetrieved();
    if (($user = $entity->get('user_id')) && $user->entity) {
      $row['user'] = $user->entity->toLink($user->entity->label());
    }
    return $row + parent::buildRow($entity);
  }

}
