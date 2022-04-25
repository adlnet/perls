<?php

namespace Drupal\perls_core;

use Drupal\Core\Database\Connection;
use Drupal\xapi\XapiStatementHelper;

/**
 * Help to sync the history records with statement.
 */
class HistoryHelper {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Statement helper from xapi module.
   *
   * @var \Drupal\xapi\XapiStatementHelper
   */
  protected XapiStatementHelper $statementHelper;

  /**
   * A helper class for history module.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\xapi\XapiStatementHelper $statement_helper
   *   Helper service to manage statements.
   */
  public function __construct(
    Connection $database,
    XapiStatementHelper $statement_helper) {
    $this->database = $database;
    $this->statementHelper = $statement_helper;
  }

  /**
   * Determines that history record exists.
   *
   * @param string $nid
   *   A node ID.
   * @param string $user_id
   *   A user id.
   *
   * @return bool
   *   Indicates that the history record exists.
   */
  public function historyExists($nid, $user_id) {
    $exists = &drupal_static(__FUNCTION__, []);

    if ($exists) {
      return $exists;
    }
    else {
      $query = $this->database->select('history', 'h');
      $query->fields('h');
      $query->condition('nid', (int) $nid);
      $query->condition('uid', (int ) $user_id);
      $exists = (bool) $query->execute()->fetchCol();
    }
    return $exists;
  }

  /**
   * Update an existing history record.
   *
   * @param int $nid
   *   A node ID.
   * @param int $user_id
   *   A user id.
   * @param string $timestamp
   *   A timestamp when the record was created.
   */
  public function updateHistory($nid, $user_id, $timestamp) {
    $this->database->update('history')
      ->fields([
        'timestamp' => $timestamp,
      ])
      ->condition('nid', (int) $nid)
      ->condition('uid', (int ) $user_id)
      ->execute();
  }

  /**
   * Add a new history record to table.
   *
   * @param string $nid
   *   A node ID.
   * @param string $user_id
   *   A user id.
   * @param string $timestamp
   *   A timestamp when the record was created.
   */
  public function insertHistory($nid, $user_id, $timestamp) {
    $this->database->insert('history')
      ->fields([
        'timestamp' => $timestamp,
        'uid' => $user_id,
        'nid' => $nid,
      ])
      ->execute();
  }

  /**
   * Sync the history records between statement and drupal.
   *
   * @param object $statement
   *   The statement object.
   */
  public function historySync($statement) {
    $timestamp = time();
    if (isset($statement->timestamp) && is_int($statement->timestamp)) {
      $timestamp = $statement->timestamp;
    }
    elseif (isset($statement->timestamp) && strtotime($statement->timestamp) != FALSE) {
      $timestamp = strtotime($statement->timestamp);
    }

    $node = $this->statementHelper->getContentFromState($statement);
    $user = $this->statementHelper->getUserFromStatement($statement);
    if ($this->historyExists($node->id(), $user->id())) {
      $this->updateHistory($node->id(), $user->id(), $timestamp);
    }
    else {
      $this->insertHistory($node->id(), $user->id(), $timestamp);
    }
  }

}
