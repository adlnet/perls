<?php

namespace Drupal\perls_content_management\Plugin\Validation\Constraint;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * This class validates the reference field has only one reference per test.
 */
class UniqueTestReferenceValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * UniqueTestReferenceValidator constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal entity type manager service.
   * @param \Drupal\Core\Database\Connection $database
   *   Drupal database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    if (isset($items) && empty($items->referencedEntities())) {
      return;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items->getEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $test_ids = [];
    foreach ($items->referencedEntities() as $ref_entity) {
      if ($ref_entity->bundle() === 'test') {
        $test_ids[] = $ref_entity->id();
      }
    }

    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $this->entityTypeManager->getStorage($entity_type_id)->getTableMapping();
    $field_table_name = $table_mapping->getDedicatedDataTableName($items->getFieldDefinition()->getFieldStorageDefinition());

    $tests = NULL;
    $ref_value_column = $items->getFieldDefinition()->getName() . '_target_id';
    if (!empty($test_ids)) {
      $query = $this->database->select($field_table_name, 'ref_field')
        ->fields('ref_field', [$ref_value_column])
        ->condition($ref_value_column, $test_ids, 'IN');

      // If we try to add the test to a new course we cannot filter out the
      // current id because it doesn't have at this point.
      if (!$entity->isNew()) {
        $query->condition('entity_id', $entity->id(), '<>');
      }

      $tests = $query->execute()->fetchCol();
    }

    if (!empty($tests)) {
      $entities = $items->referencedEntities();
      $labels = '';
      foreach ($entities as $ref_entity) {
        if ($ref_entity->bundle() === 'test' && in_array($ref_entity->id(), $tests)) {
          if (empty($labels)) {
            $labels = $ref_entity->label();
          }
          else {
            $labels = sprintf('%s, %s', $labels, $ref_entity->label());
          }
        }
      }

      $this->context->addViolation($constraint->message, [
        '@entity_type_ref' => $labels,
        '@entity_type' => $entity->bundle(),
      ]);
    }
  }

}
