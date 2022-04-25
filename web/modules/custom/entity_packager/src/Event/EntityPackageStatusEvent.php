<?php

namespace Drupal\entity_packager\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * This event notifies the system of a nodes package status.
 */
class EntityPackageStatusEvent extends Event {

  const EVENT_ID = 'entity_package_status';
  const STATUS_UNPACKAGED = 'Unpackaged';
  const STATUS_QUEUED = 'Queued';
  const STATUS_BLOCKED = 'Blocked';
  const STATUS_FAILED = 'Failed';
  const STATUS_COMPLETED = 'Packaged';

  /**
   * The entity which will be packaged.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The status of entity package.
   *
   * @var string
   */
  protected $status = EntityPackageStatusEvent::STATUS_UNPACKAGED;

  /**
   * Additional information about the current status.
   *
   * @var string
   */
  protected $information = '';

  /**
   * EntityPrePackageEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The content which will be packaged.
   * @param string $status
   *   The current status of the packaged entity.
   * @param string $information
   *   Additional information about current status.
   */
  public function __construct(EntityInterface $entity, string $status, $information = '') {
    $this->entity = $entity;
    $this->status = $status;
    $this->information = $information;
  }

  /**
   * This entity will be packaged.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   A drupal entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * The current status of this packaged entity.
   *
   * @return string
   *   The current status of this packaged entity.
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * The current context of status or additional information.
   *
   * @return string
   *   The additional information field.
   */
  public function getInformation() {
    return $this->information;
  }

}
