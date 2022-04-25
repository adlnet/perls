<?php

namespace Drupal\entity_packager\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for theme color changed event.
 */
class SiteColorChangedSubscriber implements EventSubscriberInterface {

  /**
   * Queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queue;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SiteColorChangedSubscriber constructor.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   Queue factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(QueueFactory $queue, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->queue = $queue;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['colorChanged'];
    $events[ConfigEvents::DELETE][] = ['colorChanged'];

    return $events;
  }

  /**
   * React to theme color change.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The config change event.
   */
  public function colorChanged(ConfigCrudEvent $event) {
    // We need to check that we re-queue all node ones, because some cases the
    // color form save two times.
    $generated = &drupal_static(__FUNCTION__);
    if (!isset($generated) && strpos($event->getConfig()->getName(), 'color.theme.') === 0) {
      $this->queueAllContent();
      $generated = TRUE;
    }
  }

  /**
   * Add all necessary content to queue for regenerate.
   */
  protected function queueAllContent() {
    $content_types = $this->configFactory
      ->get('entity_packager.page_settings')
      ->get('content_types');

    $entity_query = $this->entityTypeManager->getStorage('node')->getQuery()->accessCheck(FALSE);
    $query = $entity_query
      ->condition('type', $content_types, 'IN')
      ->condition('status', 1);
    $nids = $query->execute();

    foreach ($nids as $nid) {
      $queue = $this->queue->get('node_package_generate_queue');
      $queue->createItem(['entity_type' => 'node', 'entity_id' => $nid]);
    }

  }

}
