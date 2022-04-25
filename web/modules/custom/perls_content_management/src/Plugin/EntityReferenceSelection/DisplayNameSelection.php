<?php

namespace Drupal\perls_content_management\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Display Name implementation of the Entity Reference by user.
 *
 * @EntityReferenceSelection(
 *   id = "default:display_name_selection",
 *   label = @Translation("User filter by Display Name"),
 *   entity_types = {"user"},
 *   group = "users",
 *   weight = 3
 * )
 */
class DisplayNameSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    // This filter is dependent/intended to be used with autocompletes that use
    // users_field_data as their base.
    $query->condition('field_name', $match, $match_operator);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->getConfiguration()['target_type'];

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $options[$bundle][$entity_id] = $entity->getDisplayName();
    }

    return $options;
  }

}
