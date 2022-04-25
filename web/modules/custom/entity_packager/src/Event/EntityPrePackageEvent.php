<?php

namespace Drupal\entity_packager\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * This event allow that a entity won't be packaged.
 */
class EntityPrePackageEvent extends Event {

  const EVENT_ID = 'pre_entity_packaging';

  /**
   * The entity which will be packaged.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * This flag indicate that the entity should be packaged.
   *
   * @var bool
   */
  protected $packaging = TRUE;

  /**
   * EntityPrePackageEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The content which will be packaged.
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
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
   * Return with the current status of packaging property.
   *
   * @return bool
   *   The status of the packaging property.
   */
  public function isNeedPackaging(): bool {
    return $this->packaging;
  }

  /**
   * Set the packaging property.
   *
   * @param bool $flag
   *   TRUE if it needs to be packaged otherwise TRUE.
   *
   * @return $this
   */
  public function setPackaging(bool $flag) {
    $this->packaging = $flag;
    return $this;
  }

}
