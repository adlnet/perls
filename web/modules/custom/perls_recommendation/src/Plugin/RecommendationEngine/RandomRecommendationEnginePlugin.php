<?php

namespace Drupal\perls_recommendation\Plugin\RecommendationEngine;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\perls_recommendation\Recommendation\Recommendation;
use Drupal\perls_recommendation\RecommendationEnginePluginBase;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Random Recommendation engine plugin.
 *
 * @RecommendationEngine(
 *   id = "random_recommendation_engine",
 *   label = @Translation("Random Recommendation Engine"),
 *   description = @Translation("Recommends Random content to users.")
 * )
 */
class RandomRecommendationEnginePlugin extends RecommendationEnginePluginBase {
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
    $query = $this->database->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->condition('status', '1')
      ->range(0, $count)
      ->orderRandom();
    $results = $query->execute()->fetchCol();

    if (!empty($results)) {
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($results);
      foreach ($nodes as $id => $node) {
        $recommendation = new Recommendation(
          $id,
          $node,
          $node->getType(),
          1,
          'Random Recommendation',
          $this->getBaseId()
        );
        $recommendations[$id] = $recommendation;
      }
    }
    return $recommendations;
  }

}
