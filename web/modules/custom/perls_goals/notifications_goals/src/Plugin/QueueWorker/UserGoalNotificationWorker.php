<?php

namespace Drupal\notifications_goals\Plugin\QueueWorker;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\notifications\Service\ExtendedFirebaseMessageService;
use Drupal\perls_goals\GoalCalculator;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Tasks for Learning.
 *
 * @QueueWorker(
 *   id = "user_goal_notification",
 *   title = @Translation("Send goal notifications to users."),
 *   cron = {"time" = 30}
 * )
 */
class UserGoalNotificationWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * A drupal user storage.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $userStorage;

  /**
   * User goal notification helper service.
   *
   * @var \Drupal\perls_goals\GoalCalculator
   */
  protected $goalCalculator;

  /**
   * A firebase service to send notification message.
   *
   * @var \Drupal\notifications\Service\ExtendedFirebaseMessageService
   */
  protected $firebase;

  /**
   * Config manager service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Drupal datetime service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * Creates a queue worker.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $user_storage
   *   A drupal user handler.
   * @param \Drupal\perls_goals\GoalCalculator $goal_calculator
   *   A helper service to manage the goal notifications.
   * @param \Drupal\notifications\Service\ExtendedFirebaseMessageService $firebase
   *   A firebase service to send notifications.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config manager service.
   * @param \Drupal\Component\Datetime\Time $time
   *   Drupal datetime service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityStorageInterface $user_storage,
    GoalCalculator $goal_calculator,
    ExtendedFirebaseMessageService $firebase,
    ConfigFactoryInterface $config,
    Time $time
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->userStorage = $user_storage;
    $this->goalCalculator = $goal_calculator;
    $this->firebase = $firebase;
    $this->config = $config;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('perls_goals.goals_calculate'),
      $container->get('notifications.firebase.message'),
      $container->get('config.factory'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $user = $this->userStorage->load($data['uid']);
    $goal_fields = $this->config->get('perls_goals.settings')->get('fields.field_values');
    $notification_settings = $this->config->get('notifications_goals.settings');
    $notification_messages = $notification_settings->get('messages');
    $open_custom_goal_text = $this->goalCalculator->checkCustomGoals($user);

    if ($open_custom_goal_text !== NULL) {
      $this->firebase->sendPushNotification(
        $this->t('Personal Goal'),
        $open_custom_goal_text,
        $data['uid'],
        ['action' => 'goal']
      );
      return;
    }

    $closest_goal = $this->goalCalculator->checkGoal($user, $goal_fields);

    if ($closest_goal === 'all') {
      $message = $notification_messages[$closest_goal];
    }
    elseif (isset($closest_goal['goal_name'])) {
      // @codingStandardsIgnoreStart
      $message = t($notification_messages[$closest_goal['goal_name']], [
        '@count' => $closest_goal['missing'],
        '@score' => $closest_goal['score'],
        '@goal' => $closest_goal['goal'],
      ]);
      // @codingStandardsIgnoreEnd
    }
    else {
      $message = $notification_messages['none'];
    }

    if (!empty($message)) {
      $this->firebase->sendPushNotification(
        $this->t('Personal Goal'),
        $message,
        $data['uid'],
        ['action' => 'goal']
      );
    }
  }

}
