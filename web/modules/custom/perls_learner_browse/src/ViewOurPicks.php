<?php

namespace Drupal\perls_learner_browse;

use Drupal\Core\Database\Connection;

/**
 * Utility functions for the "For You" (Our Picks) view.
 */
class ViewOurPicks {

  /**
   * Drupal database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new ViewOurPicks object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The current connection to the database.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Decides that the current user has topics fields.
   *
   * @param int $uid
   *   A drupal user id.
   *
   * @return bool
   *   True if user has topics otherwise false.
   */
  public function userHasTopics($uid) {
    $query = $this->database->select('user__field_interests', 'ufi');
    $query->join('taxonomy_index', 'ti', 'ti.tid = ufi.field_interests_target_id');
    $query->fields('ti', ['nid']);
    $query->join('node_field_data', 'nfd', 'nfd.nid = ti.nid');
    $query->condition('nfd.status', '1');
    $query->condition('ufi.bundle', 'user');
    $query->addTag('node_access');
    $query->condition('ufi.entity_id', $uid);

    return (boolean) $query->countQuery()->execute()->fetchField();
  }

}
