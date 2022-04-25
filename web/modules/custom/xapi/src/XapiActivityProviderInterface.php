<?php

namespace Drupal\xapi;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines an interface for communicating between Drupal and xAPI activities.
 */
interface XapiActivityProviderInterface {

  /**
   * Retrieves an xAPI Activity for the specified entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Drupal entity.
   *
   * @return array
   *   The xAPI Activity.
   */
  public function getActivity(EntityInterface $entity): array;

  /**
   * Loads a Drupal entity based on the xAPI Activity.
   *
   * This only loads Drupal entities based on their canonical
   * activity ID. Uploaded packages may have their own activity IDs.
   *
   * You probably want to use `xapi.xapi_statement_helper`.
   *
   * @param array $activity
   *   The xAPI Activity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The Drupal entity.
   *
   * @see XapiStatementHelper::getEntityFromActivity()
   */
  public function getEntity(array $activity): ?EntityInterface;

}
