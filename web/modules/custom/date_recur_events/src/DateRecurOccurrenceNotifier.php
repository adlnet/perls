<?php

namespace Drupal\date_recur_events;

use Drupal\date_recur\DateRange;
use Drupal\date_recur\DateRecurOccurrences;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\date_recur_events\Event\EntityOccurrenceEvent;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;

/**
 * Handles dispatching events when entity occurrences are starting or ending.
 */
class DateRecurOccurrenceNotifier implements DateRecurOccurrenceNotifierInterface {

  const EVENT_STARTING_WITHIN = 1;
  const EVENT_ENDING_WITHIN = 2;
  const EVENT_STARTING_AND_ENDING_WITHIN = 3;

  /**
   * Logging channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher definition.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * Constructs a new RecurringDateChecker object.
   */
  public function __construct(LoggerChannelInterface $logger, EntityTypeManagerInterface $entity_type_manager, Connection $database, ContainerAwareEventDispatcher $event_dispatcher) {
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritDoc}
   */
  public function dispatchStartingEvents(int $since, string $length = 'now') {
    $occurrences = $this->getOccurrencesWithinRange(static::getDateRangeFromTimeFrame($since, $length), static::EVENT_STARTING_WITHIN);
    $this->dispatchEventsForOccurrences($occurrences, EntityOccurrenceEvent::EVENT_NAME_STARTING);
  }

  /**
   * {@inheritDoc}
   */
  public function dispatchEndingEvents(int $since, string $length = 'now') {
    $occurrences = $this->getOccurrencesWithinRange(static::getDateRangeFromTimeFrame($since, $length), static::EVENT_ENDING_WITHIN);
    $this->dispatchEventsForOccurrences($occurrences, EntityOccurrenceEvent::EVENT_NAME_ENDING);
  }

  /**
   * {@inheritDoc}
   */
  public function getEntitiesStartingWithinRange(DateRange $range): array {
    return array_map(function ($occurrence) {
      return $occurrence['entity'];
    }, $this->getOccurrencesWithinRange($range, static::EVENT_STARTING_WITHIN));
  }

  /**
   * {@inheritDoc}
   */
  public function getEntitiesEndingWithinRange(DateRange $range): array {
    return array_map(function ($occurrence) {
      return $occurrence['entity'];
    }, $this->getOccurrencesWithinRange($range, static::EVENT_ENDING_WITHIN));
  }

  /**
   * Generates a DateRange object from a time frame.
   *
   * @param int $timestamp
   *   The timestamp of the start of the time frame.
   * @param string $length
   *   A relative length of the time frame (using PHP relative date format).
   *
   * @return \Drupal\date_recur\DateRange
   *   The date range.
   *
   * @see https://www.php.net/manual/en/datetime.formats.relative.php
   */
  protected static function getDateRangeFromTimeFrame(int $timestamp, string $length): DateRange {
    $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $start = new \DateTime();
    $start->setTimestamp($timestamp);
    $start->setTimezone($timezone);
    return new DateRange(
      $start,
      new \DateTime($length, $timezone),
    );
  }

  /**
   * Dispatches EntityOccurrenceEvent's for each occurrence.
   *
   * @param array[] $occurrences
   *   Entity occurrences.
   * @param string $type
   *   The event type (either starting or ending).
   */
  protected function dispatchEventsForOccurrences(array $occurrences, string $type) {
    $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);

    foreach ($occurrences as $occurrence) {
      $range = new DateRange(
        new \DateTime($occurrence['start_time'], $timezone),
        new \DateTime($occurrence['end_time'], $timezone),
      );
      $event = new EntityOccurrenceEvent($occurrence['entity'], $occurrence['field'], $range);
      $this->eventDispatcher->dispatch($type, $event);
      $this->logger->notice('%entity is %event', [
        '%entity' => $event->getEntity()->label(),
        '%event' => $type === EntityOccurrenceEvent::EVENT_NAME_STARTING ? 'starting' : 'ending',
        'link' => $event->getEntity()->toLink('view')->toString(),
      ]);
    }
  }

  /**
   * Returns occurrences within the specified date range.
   *
   * Each occurrence contains the entity ID, starting time, ending time,
   * field, and entity.
   *
   * @param \Drupal\date_recur\DateRange $range
   *   The date range.
   * @param int $approach
   *   Whether to query the starting or ending time.
   *
   * @return array[]
   *   An array of associative arrays containing entity occurrences.
   *
   *   * entity_id -- The entity ID.
   *   * start_time -- The entity starting time.
   *   * end_time -- The entity ending time.
   *   * field -- The name of the field containing the date range.
   *   * entity -- The entity.
   */
  protected function getOccurrencesWithinRange(DateRange $range, int $approach): array {
    /** @var array[][] $occurrences_by_field */
    $occurrences_by_field = array_map(function ($field) use ($range, $approach) {
      $occurrences = $this->queryOccurrences($field, $range, $approach);
      foreach ($occurrences as $entity_id => &$occurrence) {
        $occurrence['field'] = $field->getName();
        $occurrence['entity'] = $this->entityTypeManager
          ->getStorage($field->getTargetEntityTypeId())
          ->load($entity_id);
      }
      return $occurrences;
    }, $this->getRecurringDateFields());

    return array_merge(...array_values($occurrences_by_field));
  }

  /**
   * Queries for entity occurrences within a given date range.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field
   *   Queries for entities containing this field.
   * @param \Drupal\date_recur\DateRange $date_range
   *   The date range.
   * @param int $approach
   *   Whether to query against the starting or ending time (or both).
   *
   * @return int[]
   *   An array of IDs of entities that occur within the date range.
   *
   * @throws InvalidArgumentException
   *   Thrown when the query approach is invalid.
   */
  protected function queryOccurrences(FieldStorageDefinitionInterface $field, DateRange $date_range, int $approach): array {
    $occurrences = DateRecurOccurrences::getOccurrenceCacheStorageTableName($field);

    $range = [
      $date_range->getStart()->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      $date_range->getEnd()->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
    ];

    $starting_column = "{$field->getName()}_value";
    $ending_column = "{$field->getName()}_end_value";

    $query = $this->database->select($occurrences, 'occurrences');
    $query->addField('occurrences', 'entity_id');
    $query->addField('occurrences', $starting_column, 'start_time');
    $query->addField('occurrences', $ending_column, 'end_time');

    switch ($approach) {
      case static::EVENT_STARTING_WITHIN:
        $query->condition("occurrences.$starting_column", $range, 'BETWEEN');
        break;

      case static::EVENT_ENDING_WITHIN:
        $query->condition("occurrences.$ending_column", $range, 'BETWEEN');
        break;

      case static::EVENT_STARTING_AND_ENDING_WITHIN:
        [$starting, $ending] = $range;
        $query
          ->condition("occurrences.$starting_column", $starting, '>=')
          ->condition("occurrences.$ending_column", $ending, '<=');
        break;

      default:
        throw new \InvalidArgumentException('Invalid query approach');
    }

    return $query->execute()->fetchAllAssoc('entity_id', \PDO::FETCH_ASSOC);
  }

  /**
   * Retrieve all recurring date fields.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   *   Instances of recurring date fields.
   */
  protected function getRecurringDateFields(): array {
    return $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->loadByProperties([
        'type' => 'date_recur',
        'deleted' => FALSE,
        'status' => 1,
      ]);
  }

}
