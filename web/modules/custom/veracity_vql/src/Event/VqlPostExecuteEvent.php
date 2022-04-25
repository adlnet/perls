<?php

namespace Drupal\veracity_vql\Event;

/**
 * Dispatched after VQL has been executed and we have a result.
 */
class VqlPostExecuteEvent extends VqlEventBase {
  const EVENT_NAME = 'veracity_vql.vql_post_execute';

  /**
   * The VQL result.
   *
   * @var array
   */
  protected $result;

  /**
   * Creates a new VqlPostExecuteEvent.
   *
   * @param string $query
   *   The VQL query.
   * @param array $result
   *   The VQL result.
   */
  public function __construct(string $query, array $result) {
    parent::__construct($query);
    $this->result = &$result;
  }

  /**
   * Gets the VQL result.
   *
   * @return array
   *   The result.
   */
  public function &getResult(): array {
    return $this->result;
  }

}
