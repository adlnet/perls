<?php

namespace Drupal\xapi\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Create a new event for statement send.
 */
class XapiStatementReceived extends Event {

  const EVENT_NAME = 'xapi_statement_received';

  /**
   * An xApi statement object.
   *
   * @var object
   */
  protected $statement;

  /**
   * Constructs the object.
   *
   * @param object $statement
   *   An xAPI statement that we will send to LRS server.
   */
  public function __construct($statement) {
    $this->statement = $statement;
  }

  /**
   * Gives back the statement object.
   */
  public function getStatement() {
    return $this->statement;
  }

}
