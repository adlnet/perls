<?php

namespace Drupal\perls_learner_state\Plugin;

use Drupal\perls_learner_state\PerlsLearnerStatementFlag;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\flag\Entity\Flagging;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\xapi\XapiVerb;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\xapi\XapiStatement;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\xapi\LRSRequestGenerator;
use Drupal\xapi\XapiActorIFIManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\xapi\XapiActivityProviderInterface;

/**
 * A base class for xapi state plugin.
 *
 * @package Drupal\perls_learner_state\Plugin
 */
abstract class XapiStateBase extends PluginBase implements ContainerFactoryPluginInterface {
  const OPERATION_ADD = 'add';
  const OPERATION_REMOVE = 'remove';

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity which belongs to this statement.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $statementContent;

  /**
   * The statement object.
   *
   * @var \Drupal\xapi\XapiStatement
   */
  public $statement;

  /**
   * Indicates that the state is an add or remove operation.
   *
   * @var string
   */
  protected $operation = self::OPERATION_ADD;

  /**
   * A helper service to manage sync the statement and flags.
   *
   * @var \Drupal\perls_learner_state\PerlsLearnerStatementFlag
   */
  protected $flagStatementHelper;

  /**
   * The request generator service.
   *
   * @var \Drupal\xapi\LRSRequestGenerator
   */
  protected $requestGenerator;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new learner state plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\xapi\XapiActorIFIManager $ifi_manager
   *   The actor IFI manager.
   * @param \Drupal\xapi\XapiActivityProviderInterface $activity_provider
   *   The actor provider.
   * @param \Drupal\perls_learner_state\PerlsLearnerStatementFlag $flag_statement_helper
   *   A helper service to manage sync between statement and flags.
   * @param \Drupal\xapi\LRSRequestGenerator $request_generator
   *   The request generator service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccountInterface $current_user,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    XapiActorIFIManager $ifi_manager,
    XapiActivityProviderInterface $activity_provider,
    PerlsLearnerStatementFlag $flag_statement_helper,
    LRSRequestGenerator $request_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->statement = new XapiStatement($current_user, $config_factory, $ifi_manager, $activity_provider);
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
    $this->flagStatementHelper = $flag_statement_helper;
    $this->requestGenerator = $request_generator;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('plugin.manager.xapi_actor_ifi'),
      $container->get('xapi.activity_provider'),
      $container->get('perls_learner_state.flagging_helper'),
      $container->get('lrs.request_generator'),
    );
  }

  /**
   * Prepare a verb object with the proper properties and set it in statement.
   */
  protected function prepareVerb() {
    switch ($this->operation) {
      case self::OPERATION_REMOVE:
        $this->statement->setVerb($this->getRemoveVerb());
        break;

      case self::OPERATION_ADD:
      default:
        $this->statement->setVerb($this->getAddVerb());
        break;
    }
  }

  /**
   * Method to return the flag property of this plugin.
   *
   * @return string
   *   The flag ID associated to this state.
   */
  public function getFlagName() {
    return $this->getPluginDefinition()['flag'];
  }

  /**
   * Gets the verb for enabling this learner state.
   *
   * @return \Drupal\xapi\XapiVerb|null
   *   The verb associated with enabling this flag.
   */
  protected function getAddVerb(): ?XapiVerb {
    return $this->getPluginDefinition()['add_verb'];
  }

  /**
   * Gets the verb for removing this learner state.
   *
   * @return \Drupal\xapi\XapiVerb|null
   *   Gives back the add_verb setting of plugin.
   */
  protected function getRemoveVerb(): ?XapiVerb {
    return $this->getPluginDefinition()['remove_verb'];
  }

  /**
   * Gets the content associated with this instance.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   A drupal entity.
   */
  public function getStatementContent(): ?EntityInterface {
    return $this->statementContent;
  }

  /**
   * Sets the content associated with this instance.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A drupal entity.
   */
  public function setStatementContent(EntityInterface $entity) {
    $this->statementContent = $entity;
  }

  /**
   * Give plugins a chance to process extradata.
   *
   * @param object $extra_data
   *   The js pass some extra data.
   */
  public function processExtraData($extra_data) {
    // By default we do nothing with this.
  }

  /**
   * Prepares the statement.
   *
   * Implementations should override this to customize the statement.
   *
   * @param int $timestamp
   *   A timestamp for the event.
   * @param \Drupal\user\UserInterface $user
   *   The actor; defaults to the current user.
   */
  protected function prepareStatement(int $timestamp = NULL, UserInterface $user = NULL) {
    if (!$user) {
      $user = User::load($this->currentUser->id());
    }

    // Actor.
    $this->statement->setActor($user);

    // Verb.
    $this->prepareVerb();

    // Activity.
    if (!empty($this->getStatementContent())) {
      $this->statement->setActivity($this->getStatementContent());
    }

    if ($timestamp) {
      $this->statement->setTimestamp($timestamp);
    }

    $context = [
      'entity' => $this->getStatementContent(),
    ];
    $this->moduleHandler->alter('xapi_statement_template', $this->statement, $context);
  }

  /**
   * Populate a statement object for LRS.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The flagged content.
   * @param int $timestamp
   *   A timestamp.
   * @param \Drupal\user\UserInterface $user
   *   Optioonal. A drupal user.
   *
   * @return \Drupal\xapi\XapiStatement|null
   *   The populated statement.
   */
  public function getReadyStatement(?EntityInterface $entity, int $timestamp = NULL, UserInterface $user = NULL): ?XapiStatement {
    if ($entity !== NULL) {
      if (!$this->supportsContentType($entity)) {
        return NULL;
      }
      $this->setStatementContent($entity);
    }

    $this->prepareStatement($timestamp, $user);
    return $this->statement;
  }

  /**
   * Use a Flag object to populate a xapi statement.
   */
  public function prepareStatementFromFlag(Flagging $flagging) {
    $timestamp = $flagging->get('created')->value;
    return $this->getReadyStatement($flagging->getFlaggable(), $timestamp, $flagging->getOwner());
  }

  /**
   * Sets the operation being performed.
   *
   * @param string $operation
   *   The state operation. It should be add or remove (defaults to add).
   */
  public function setOperation($operation) {
    if ($operation !== self::OPERATION_ADD && $operation !== self::OPERATION_REMOVE) {
      throw new \InvalidArgumentException('Invalid operation specified');
    }
    $this->operation = $operation;
  }

  /**
   * Get the statement builder associated with this state.
   *
   * @return \Drupal\xapi\XapiStatement
   *   The statement builder.
   */
  public function getStatement() {
    return $this->statement;
  }

  /**
   * Create a new flag based on plugin settings.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A drupal entity.
   * @param \Drupal\user\UserInterface $user
   *   The User entity.
   * @param array $extra_data
   *   An assocative array of field values to add to flag.
   * @param object $statement
   *   The xapi statement that triggered the flag.
   *
   * @return \Drupal\flag\FlaggingInterface|null
   *   A flagging object.
   */
  public function flagSync(EntityInterface $entity, UserInterface $user, array $extra_data, $statement = NULL) {
    if (!$this->supportsContentType($entity)) {
      return NULL;
    }
    return $this->flagStatementHelper->createNewFlag($entity, $this->getFlagName(), $user, $extra_data);
  }

  /**
   * This method is called when a remove verb xapi statement is recieved.
   *
   * This method will only be called if the plugin has opted to recieve
   * xapi notifications and has a remove_verb specified.
   */
  public function unflag(EntityInterface $entity, UserInterface $user) {
    if (!$this->supportsContentType($entity)) {
      return NULL;
    }
    return $this->flagStatementHelper->deleteFlag($entity, $this->getFlagName(), $user);
  }

  /**
   * The node types this plugin accepts for flagging.
   */
  public function supportsContentType(EntityInterface $entity) {
    return TRUE;
  }

  /**
   * Send the generated statement to LRS endpoint.
   *
   * @param int $uid
   *   Set the user id this way you can send the request as different user.
   */
  public function sendStatement($uid = NULL) {
    if (!$uid && $this->currentUser->isAnonymous()) {
      $uid = 1;
    }

    $this->requestGenerator->sendStatements([$this->statement], $uid);
  }

}
