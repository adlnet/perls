<?php

namespace Drupal\xapi_reporting;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\xapi\XapiStatement;
use Drupal\xapi\XapiActorIFIManager;
use Drupal\xapi\XapiActivityProviderInterface;

/**
 * Prepare the necessary part for a XAPI statement.
 */
class XapiStatementCreator {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Xapi IFI type manager.
   *
   * @var \Drupal\xapi\XapiActorIFIManager
   */
  protected $ifiManager;

  /**
   * The activity provider.
   *
   * @var \Drupal\xapi\XapiActivityProviderInterface
   */
  protected $activityProvider;

  /**
   * The current module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * This class help to create XAPI statements.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\xapi\XapiActorIFIManager $ifi_manager
   *   The current IFI manager.
   * @param \Drupal\xapi\XapiActivityProviderInterface $activity_provider
   *   The activity provider.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The current module handler.
   */
  public function __construct(
    AccountInterface $current_user,
    ConfigFactoryInterface $config_factory,
    XapiActorIFIManager $ifi_manager,
    XapiActivityProviderInterface $activity_provider,
    ModuleHandlerInterface $module_handler) {
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->ifiManager = $ifi_manager;
    $this->activityProvider = $activity_provider;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Create a very basic statement which only contains the actor.
   *
   * @return \Drupal\xapi\XapiStatement
   *   A statement with the current user set as the actor.
   */
  public function getTemplateStatement(): XapiStatement {
    $statement = $this->createStatement();
    $context = [];
    $this->moduleHandler->alter('xapi_statement_template', $statement, $context);
    return $statement;
  }

  /**
   * Create a statement template with default values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A Drupal entity.
   *
   * @return \Drupal\xapi\XapiStatement
   *   An xAPI statement with the actor and object set.
   */
  public function getEntityTemplateStatement(EntityInterface $entity): XapiStatement {
    $statement = $this->createStatement()
      ->setActivity($entity);

    $context = ['entity' => $entity];
    $this->moduleHandler->alter('xapi_statement_template', $statement, $context);

    return $statement;
  }

  /**
   * Creates a new xAPI statement.
   *
   * @return \Drupal\xapi\XapiStatement
   *   The xAPI statement builder.
   */
  protected function createStatement(): XapiStatement {
    $statement = new XapiStatement(
      $this->currentUser,
      $this->configFactory,
      $this->ifiManager,
      $this->activityProvider
    );
    $statement->setActorToCurrentUser();
    return $statement;
  }

}
