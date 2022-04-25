<?php

namespace Drupal\date_recur_events\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;
use Drupal\date_recur\DateRange;

/**
 * Dispatched when an entity is starting or ending.
 */
class EntityOccurrenceEvent extends Event {
  const EVENT_NAME_STARTING = 'date_recur.starting';
  const EVENT_NAME_ENDING = 'date_recur.ending';

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The name of the field containing the date information.
   *
   * @var string
   */
  protected $field;

  /**
   * The starting and ending date of the occurrence.
   *
   * @var \Drupal\date_recur\DateRange
   */
  protected $date;

  /**
   * Creates a new EntityOccurrenceEvent.
   */
  public function __construct(EntityInterface $entity, string $field, DateRange $date) {
    $this->entity = $entity;
    $this->field = $field;
    $this->date = $date;
  }

  /**
   * Gets the entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Gets the field containing the date for the occurrence.
   *
   * @return string
   *   The field name.
   */
  public function getField(): string {
    return $this->field;
  }

  /**
   * Gets the starting date for the occurrence.
   *
   * @return \DateTimeInterface
   *   The starting date.
   */
  public function getStartDate(): \DateTimeInterface {
    return $this->getDateRange()->getStart();
  }

  /**
   * Gets the ending date for the occurrence.
   *
   * @return \DateTimeInterface
   *   The ending date.
   */
  public function getEndDate(): \DateTimeInterface {
    return $this->getDateRange()->getEnd();
  }

  /**
   * Gets the date range (start and end) for the occurrence.
   *
   * @return \Drupal\date_recur\DateRange
   *   The date range.
   */
  public function getDateRange(): DateRange {
    return $this->date;
  }

}
