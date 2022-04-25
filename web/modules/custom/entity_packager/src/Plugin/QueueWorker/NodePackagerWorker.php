<?php

namespace Drupal\entity_packager\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\entity_packager\EntityPackager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker which save the node pages into a zip file.
 *
 * @QueueWorker(
 *   id = "node_package_generate_queue",
 *   title = @Translation("Save node pages into zip"),
 *   cron = {"time" = 60}
 * )
 */
class NodePackagerWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity view saver service.
   *
   * @var \Drupal\entity_packager\EntityPackager
   */
  private $entityPackager;

  /**
   * Module logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $moduleLogger;

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_packager\EntityPackager $entity_creator
   *   The entity view saver service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Drupal logger service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityPackager $entity_creator,
    LoggerChannelFactoryInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityPackager = $entity_creator;
    $this->moduleLogger = $logger->get('entity_packager');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_packager.entity_packager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!empty($data['entity_id']) && !empty($data['entity_type'])) {
      $result = $this->entityPackager->generateZip($data['entity_type'], $data['entity_id']);
      if (!$result) {
        $this->moduleLogger->error('Unable to package @entity_type:@entity_id.', [
          '@entity_type' => $data['entity_type'],
          '@entity_id' => $data['entity_id'],
        ]);
      }
    }
  }

}
