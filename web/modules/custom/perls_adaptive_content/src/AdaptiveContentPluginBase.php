<?php

namespace Drupal\perls_adaptive_content;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an base class for Adaptive Content plugins.
 *
 * Plugins extending this class need to define a plugin definition array
 * through annotation. These definition arrays may be altered through
 * hook_perls_adaptive_content_adaptive_content_info_alter().
 * The definition includes the following keys:
 * - id: The unique, system-wide identifier of the adaptive content class.
 * - label: The human-readable name of the adaptive content class
 *   , translated.
 * - description: A human-readable description for the adaptive content class
 *   translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @AdaptiveContent(
 *   id = "my_adaptive_content_plugin",
 *   label = @Translation("My Adaptive Content plugin."),
 *   description = @Translation("A base adaptive content class that does nothing.")
 * )
 * @endcode
 *
 * @see \Drupal\perls_adaptive_content\Annotation\AdaptiveContent
 * @see \Drupal\perls_adaptive_content\AdaptiveContentPluginManager
 * @see \Drupal\perls_adaptive_content\AdaptiveContentPluginInterface
 * @see plugin_api
 */
abstract class AdaptiveContentPluginBase extends PluginBase implements ContainerFactoryPluginInterface, AdaptiveContentPluginInterface {
  /**
   * The logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config Factory Interface.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

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
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('Adaptive Content Plugin'),
      $container->get('config.factory'),
      $container->get('flag'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructor for Adaptive Content Plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger_factory,
    ConfigFactory $config_factory,
    FlagServiceInterface $flag_service,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger_factory;
    $this->configFactory = $config_factory;
    $this->flagService = $flag_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function processTestAttempt(Node $test, ParagraphInterface $test_attempt, AccountInterface $user) {
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedback(EntityInterface $test, $result, $correctly_answered, $question_count) {
    if (intval($correctly_answered) > 0) {
      return $this->t('<p>You answered @correct out of @count correctly. </p><p> Based on your score, you will advance in the course content</p>', [
        '@correct' => $correctly_answered,
        '@count' => $question_count,
      ]);
    }
    else {
      return $this->t('<p>You answered @correct out of @count correctly. </p>',
        ['@correct' => $correctly_answered, '@count' => $question_count]);
    }
  }

  /**
   * Get a list of learning objects from a course related to test.
   */
  protected function contentObjectsInCourse(NodeInterface $entity) {
    $learning_objects = [];
    $courses = $this->entityTypeManager->getStorage('node')->loadByProperties(
      [
        'type' => 'course',
        'status' => 1,
        'field_learning_content' => $entity->id(),
      ]
      );
    foreach ($courses as $course) {
      $content = array_column($course->field_learning_content->getValue(), 'target_id');
      $learning_objects = array_merge($learning_objects, array_values($content));
    }
    return $learning_objects;
  }

  /**
   * Get array of all referenced learning objects from course related to test.
   */
  protected function loadLearningObjectsInCourse(NodeInterface $entity) {
    $learning_objects = [];
    $courses = $this->entityTypeManager->getStorage('node')->loadByProperties(
      [
        'type' => 'course',
        'status' => 1,
        'field_learning_content' => $entity->id(),
      ]
      );
    foreach ($courses as $course) {
      $content = $course->field_learning_content->referencedEntities();
      $learning_objects = array_merge($learning_objects, array_values($content));
    }
    return $learning_objects;
  }

}
