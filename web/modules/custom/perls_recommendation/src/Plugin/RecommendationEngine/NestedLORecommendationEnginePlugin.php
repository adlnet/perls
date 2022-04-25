<?php

namespace Drupal\perls_recommendation\Plugin\RecommendationEngine;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\perls_learner_browse\ViewCollection;
use Drupal\perls_recommendation\Recommendation\Recommendation;
use Drupal\perls_recommendation\RecommendationEnginePluginBase;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Random Recommendation engine plugin.
 *
 * @RecommendationEngine(
 *   id = "nested_lo_recommendation_engine",
 *   label = @Translation("Nested LO Recommendation Engine"),
 *   description = @Translation("Recommends tips, flashcards and quizzes that are associated with completed content.")
 * )
 */
class NestedLORecommendationEnginePlugin extends RecommendationEnginePluginBase {
  /**
   * The database.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
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
      $container->get('logger.factory')->get('Random Recommendation Engine'),
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructor for Recommendation Engine.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger_factory,
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory);
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserRecommendations(UserInterface $user, $count = 5, $now = FALSE) {
    $recommendations = [];
    // Get $count random nodes and return them.
    $collection = new ViewCollection($count);
    $collection->addViewById('review_material', [], 'embed_1');
    $data = $collection->groupedResults();
    if (empty($data)) {
      return $recommendations;
    }
    foreach ($data[0]['content'] as $node) {
      $recommendations[$node->id()] = new Recommendation(
        $node->id(),
        $node,
        $node->getType(),
        (mt_rand() / mt_getrandmax()) * 0.5,
        'Review Items - Related to completed content',
        $this->pluginId
      );
    }
    return $recommendations;
  }

}
