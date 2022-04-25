<?php

namespace Drupal\entity_packager;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * A helper class to manage batching node package generation.
 */
class NodePackageBatchGenerator {

  /**
   * Contains the node packager settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $packagerSettings;

  /**
   * Drupal node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $nodeStorage;

  /**
   * NodePackageBatchGenerator constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal entity type manager service.
   */
  public function __construct(
  ConfigFactoryInterface $config_factory,
  EntityTypeManagerInterface $entity_type_manager) {
    $this->packagerSettings = $config_factory->get('entity_packager.page_settings');
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * Generate the entity packages.
   */
  public function generate() {
    $operation = $this->prepareBatch();
    batch_set($operation);
  }

  /**
   * Prepare an operation array for batch process.
   *
   * @return array
   *   The generate operation array.
   */
  private function prepareBatch() {
    $conditions = [
      'status' => '1',
    ];
    $node_types = $this->packagerSettings->get('content_types');

    if (!empty($node_types)) {
      $conditions['type'] = array_values($node_types);
    }
    $nids = $this->nodeStorage->loadByProperties($conditions);
    return [
      'title' => t('Generating node packages...'),
      'operations' => [
        [
          [get_class($this), 'batchProcess'], [$nids],
        ],
      ],
      'progress_message' => t('Processing nodes...'),
      'finished' => [get_class($this), 'batchFinish'],
    ];
  }

  /**
   * Generate all node packages.
   *
   * @param array $nodes
   *   List if nodes what we will pack.
   * @param mixed|array $context
   *   The bach context array.
   */
  public static function batchProcess(array $nodes, &$context) {
    if (empty($context['sandbox']['total'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['total'] = count($nodes);
      $context['sandbox']['nids'] = $nodes;
      $context['sandbox']['failed'] = 0;
    }

    $limit = 3;
    if (count($context['sandbox']['nids']) < $limit) {
      $limit = count($context['sandbox']['nids']);
    }
    $nodes = array_slice($context['sandbox']['nids'], $context['sandbox']['progress'], $limit);
    foreach ($nodes as $node) {
      $result = \Drupal::service('entity_packager.entity_packager')->generateZip('node', $node->id());
      if ($result) {
        $context['results'][] = $node->id();
      }
      else {
        $context['sandbox']['failed']++;
      }
      $context['sandbox']['progress']++;
      $context['message'] = t('@progress of @total processed. (@failed failed)', [
        '@progress' => $context['sandbox']['progress'],
        '@total' => $context['sandbox']['total'],
        '@failed' => $context['sandbox']['failed'],
      ]);
    }

    if ($context['sandbox']['progress'] < $context['sandbox']['total']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
    }
    else {
      $context['finished'] = 1;
    }
  }

  /**
   * Completed the entity package creation.
   *
   * @param bool $success
   *   TRUE if batch successfully completed.
   * @param array $results
   *   Batch results.
   * @param array $operations
   *   An array of function calls (not used in this function).
   */
  public static function batchFinish($success, array $results, array $operations) {
    if (!$success) {
      \Drupal::messenger()->addError(t('Finished with an error.'));
    }
    else {
      \Drupal::messenger()->addStatus(t('You successfully generated @count offline nodes.', ['@count' => count($results)]));
    }
  }

}
