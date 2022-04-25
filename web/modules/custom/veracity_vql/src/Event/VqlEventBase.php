<?php

namespace Drupal\veracity_vql\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Base event implementation for VQL events.
 */
abstract class VqlEventBase extends Event {
  /**
   * The VQL being executed.
   *
   * @var array
   */
  protected $query;

  /**
   * Creates a new VqlEventBase.
   *
   * @param string $query
   *   The VQL query.
   */
  public function __construct(string $query) {
    $this->query = json_decode($query, TRUE) ?? [];
  }

  /**
   * Gets the VQL query being executed.
   *
   * @return array
   *   The VQL query.
   */
  public function &getQuery(): array {
    return $this->query;
  }

  /**
   * Retrieves the "id" of a query (if it exists).
   *
   * Consider adding an "id" property to queries to make them
   * easier to identify and modify.
   *
   * @return string|null
   *   The query ID.
   */
  public function getQueryId(): ?string {
    if (!isset($this->query['id'])) {
      return NULL;
    }

    return $this->query['id'];
  }

  /**
   * Retrieves the query title (if it exists).
   *
   * @return string|null
   *   The query title.
   */
  public function getQueryTitle(): ?string {
    if (!isset($this->query['title'])) {
      return NULL;
    }

    return $this->query['title'];
  }

}
