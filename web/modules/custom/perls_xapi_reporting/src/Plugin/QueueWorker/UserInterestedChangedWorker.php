<?php

namespace Drupal\perls_xapi_reporting\Plugin\QueueWorker;

use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\perls_learner_state\Plugin\XapiStateManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Use cron to send xapi statement about new defined goals.
 *
 * @QueueWorker(
 *   id = "xapi_send_changed_interest",
 *   title = @Translation("Modified the user interest set"),
 *   cron = {"time" = 30}
 * )
 */
class UserInterestedChangedWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Xapi statement manager service.
   *
   * @var \Drupal\perls_learner_state\Plugin\XapiStateManager
   */
  protected $statementManager;

  /**
   * The Logger Service.
   *
   * @var Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * DefinedNewGoalWorker constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\perls_learner_state\Plugin\XapiStateManager $statement_manager
   *   Xapi statement manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory for this class.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    XapiStateManager $statement_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->statementManager = $statement_manager;
    $this->logger = $logger_factory->get('User Interested Cron Worker');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.state_manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $user = User::load($data['uid']);

    if ($user === NULL) {
      $this->logger
        ->error("User with id %id not found", ["%id" => $data['uid']]);
      return;
    }

    $term = Term::load($data['content_id']);

    if ($term === NULL) {
      $this->logger
        ->error("Term with id %id not found", ["%id" => $data['content_id']]);
      return;
    }

    /** @var \Drupal\perls_learner_state\Plugin\XapiState\UserChangedInterestState $statement_plugin */
    $statement_plugin = $this->statementManager->createInstance('xapi_changed_user_interest');
    /** @var \Drupal\perls_learner_state\Plugin\XapiState\UserDefineNewGoal $defined_goal_plugin */
    $statement_plugin->setOperation($data['operation']);

    $statement_plugin->getReadyStatement($term, $data['time'], $user);
    $statement_plugin->sendStatement((int) $user->id());
  }

}
