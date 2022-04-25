<?php

namespace Drupal\veracity_vql\Event;

/**
 * Dispatched just before VQL is sent to Veracity to be executed.
 */
class VqlPreExecuteEvent extends VqlEventBase {
  const EVENT_NAME = 'veracity_vql.vql_pre_execute';

  /**
   * Prepares the query to be sent to Veracity.
   *
   * @return string
   *   The query.
   */
  public function prepareQuery(): string {
    $query = $this->query;

    // Veracity expects `filter` to be an object (if it is specified).
    // When encoding an array to JSON, an empty array will be encoded as `[]`.
    // So, if `filter` is an empty array, we'll remove it from the query.
    if (isset($query['filter']) && empty($query['filter'])) {
      unset($query['filter']);
    }

    return json_encode($query);
  }

}
