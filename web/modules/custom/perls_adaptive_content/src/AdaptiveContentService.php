<?php

namespace Drupal\perls_adaptive_content;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;

/**
 * Class AdaptiveContentService provides adaptive content services.
 */
class AdaptiveContentService implements AdaptiveContentServiceInterface {
  use StringTranslationTrait;


  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The configuration factory.
   *
   * @var Drupal\Core\Config\Config
   */
  protected $config;


  /**
   * The Adaptive Content Plugin manager.
   *
   * @var Drupal\sparkelarn_adaptive_content\AdaptiveContentPluginManager
   */
  protected $adaptiveContentPluginManager;

  /**
   * The Flag Service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * Constructs a new adaptive content service object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The Current User.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param Drupal\perls_adaptive_content\AdaptiveContentPluginManager $adaptive_content_manager
   *   The adaptive content plugin manager.
   * @param Drupal\flag\FlagServiceInterface $flag_service
   *   The Flag service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The Entity Manager.
   * @param Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger interface.
   */
  public function __construct(
      AccountInterface $current_user,
      ConfigFactory $config_factory,
      AdaptiveContentPluginManager $adaptive_content_manager,
      FlagServiceInterface $flag_service,
      EntityTypeManagerInterface $entity_manager,
      LoggerChannelFactoryInterface $logger
    ) {
    $this->currentUser = $current_user;
    $this->config = $config_factory->get('recommender.settings');
    $this->adaptiveContentPluginManager = $adaptive_content_manager;
    $this->flagService = $flag_service;
    $this->entityTypeManager = $entity_manager;
    $this->logger = $logger->get('adaptive_service_logger');
  }

  /**
   * {@inheritdoc}
   */
  public function processTest(NodeInterface $node, AccountInterface $user) {
    // Only interested in tests.
    if ($node->getType() !== 'test') {
      return;
    }
    // Adaptive learning must be enabled.
    if (!$node->hasField('field_adaptive_content')) {
      return;
    }
    // Adaptive plugin must have a plugin.
    $plugin = $this->getAdaptiveContentPlugin($node->field_adaptive_content->value);
    if (!$plugin) {
      return;
    }
    // Get the latest attempt.
    $attempt = $this->getLatestAttempt($node, $user);
    if (!$attempt) {
      return;
    }
    $plugin->processTestAttempt($node, $attempt, $user);
  }

  /**
   * Get an array of all available adaptive content plugins.
   *
   * @return array
   *   An array of adaptive learning plugins.
   */
  public function getAdaptiveContentPlugins() {

    $adaptive_plugins = [];
    foreach ($this->adaptiveContentPluginManager->getDefinitions() as $name => $plugin_definition) {
      if (class_exists($plugin_definition['class'])) {
        /** @var \Drupal\perls_adaptive_content\AdaptiveContentPluginInterface $adaptive_content_plugin */
        $adaptive_content_plugin = $this->adaptiveContentPluginManager
          ->createInstance($name);

        $adaptive_plugins[$name] = $adaptive_content_plugin;
      }
      else {
        $this->logger->warning('Adaptive Content Plugin %id specifies a non-existing class %class.', [
          '%id' => $name,
          '%class' => $plugin_definition['class'],
        ]);
      }
    }
    return $adaptive_plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdaptiveContentPlugin(string $id = NULL) {
    if ($id === NULL || $id === '' || $id === '_none' || $id === "0") {
      return NULL;
    }

    $adaptive_plugins = $this->adaptiveContentPluginManager->getDefinitions();
    if (!isset($adaptive_plugins[$id])) {
      $this->logger->warning('Adaptive Content Plugin %id was not found.', [
        '%id' => $id,
      ]);
      return NULL;
    }
    // Check that class exists.
    if (!class_exists($adaptive_plugins[$id]['class'])) {
      $this->logger->warning('Adaptive Content Plugin %id specifies a non-existing class %class.', [
        '%id' => $id,
        '%class' => $adaptive_plugins[$id]['class'],
      ]);
    }
    // Load and return plugin.
    return $this->adaptiveContentPluginManager->createInstance($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getTestFeedback(EntityInterface $test, $result, $correctly_answered, $question_count) {
    // Set a basic fall back response in case of error or a call for a
    // wrong entity type.
    $feedback = $this->t(
      '<h2>@result %</h2><div>You answered <span class="correct">@correct</span> out of <span class="total">@total</span> correct.</div>',
      [
        '@result' => intval($result * 100),
        '@correct' => $correctly_answered,
        '@total' => $question_count,
      ]
    );

    if (!$this->isTestAdaptive($test)) {
      // Normally this shouldn't happen but lets return a
      // basic message in case it does.
      return $feedback;
    }
    // Get the adaptive plugin ID.
    $adaptive_id = $test->field_adaptive_content->value;

    if ($adaptive_id !== NULL && $adaptive_id !== '_none') {
      if ($plugin = $this->getAdaptiveContentPlugin($adaptive_id)) {
        $feedback = $plugin->getFeedback($test, $result, $correctly_answered, $question_count);
      }
    }
    return $feedback;
  }

  /**
   * {@inheritdoc}
   */
  public function isTestAdaptive(EntityInterface $test) {
    // Only tests can be adaptive.
    if ($test->getType() !== 'test' || !$test->hasField('field_adaptive_content')) {
      return FALSE;
    }
    $is_adaptive = $test->field_adaptive_content->value;
    if ($is_adaptive !== NULL && $is_adaptive !== '_none') {
      if ($this->getAdaptiveContentPlugin($is_adaptive)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get the lastest test attempt.
   */
  protected function getLatestAttempt(NodeInterface $node, AccountInterface $user) {
    $flag = $this->flagService->getFlagById('test_results');
    $flagging = $this->flagService->getFlagging($flag, $node, $user);
    if (!$flagging) {
      return NULL;
    }
    // Check to see if an attempt has been made.
    if (!$flagging->hasField('field_test_attempts')) {
      return NULL;
    }

    $all_attempts = $flagging->field_test_attempts;
    if (!$all_attempts || $all_attempts->count() === 0) {
      return;
    }
    // Load last attempt.
    $test_attempt = $all_attempts->get($all_attempts->count() - 1)->get('entity')->getTarget()->getValue();
    return $test_attempt;

  }

}
