<?php

namespace Drupal\perls_xapi_reporting\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi\XapiStatement;
use Drupal\xapi_reporting\XapiStatementCreator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Base event subscriber for responding to noteworthy events.
 */
abstract class BaseSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\xapi\LRSRequestGenerator definition.
   *
   * @var \Drupal\xapi\LRSRequestGenerator
   */
  protected $requestGenerator;

  /**
   * Statement creator.
   *
   * @var \Drupal\xapi_reporting\XapiStatementCreator
   */
  protected $statementCreator;

  /**
   * Constructs a new BaseSubscriber object.
   */
  public function __construct(LRSRequestGenerator $request_generator, XapiStatementCreator $statement_creator) {
    $this->requestGenerator = $request_generator;
    $this->statementCreator = $statement_creator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [];
  }

  /**
   * Creates a new statement.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   Optionally, prepare the statement with the provided entity.
   *
   * @return \Drupal\xapi\XapiStatement
   *   The new statement.
   */
  protected function createStatement(?EntityInterface $entity = NULL): XapiStatement {
    if ($entity) {
      return $this->statementCreator->getEntityTemplateStatement($entity);
    }

    return $this->statementCreator->getTemplateStatement();
  }

  /**
   * Send a single statement to the LRS.
   *
   * @param \Drupal\xapi\XapiStatement $statement
   *   The statement to send.
   * @param int|null $uid
   *   The User Id to use to send this statement (optional).
   */
  protected function sendStatement(XapiStatement $statement, $uid = NULL) {
    return $this->sendStatements([$statement], $uid);
  }

  /**
   * Send a batch of statements to the LRS.
   *
   * @param array $statements
   *   The statements to send.
   * @param int|null $uid
   *   The User Id to use to send this statement (optional).
   */
  protected function sendStatements(array $statements, $uid = NULL) {
    $this->requestGenerator->sendStatements($statements, $uid);
  }

}
