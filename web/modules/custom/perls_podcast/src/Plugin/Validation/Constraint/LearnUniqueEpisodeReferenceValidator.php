<?php

namespace Drupal\perls_podcast\Plugin\Validation\Constraint;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * This class validates the reference field has only one reference per episode.
 */
class LearnUniqueEpisodeReferenceValidator extends ConstraintValidator implements ContainerInjectionInterface {

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
   * LearnUniqueEpisodeReferenceValidator constructor.
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
    $episodeIDs = [];
    foreach ($items->referencedEntities() as $refEntity) {
      if ($refEntity->bundle() === 'podcast_episode') {
        $episodeIDs[] = $refEntity->id();
      }
    }

    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $tableMapping */
    $tableMapping = $this->entityTypeManager->getStorage($entity_type_id)->getTableMapping();
    $fieldTableName = $tableMapping->getDedicatedDataTableName($items->getFieldDefinition()->getFieldStorageDefinition());

    $episodes = NULL;
    $ref_value_column = $items->getFieldDefinition()->getName() . '_target_id';
    if (!empty($episodeIDs)) {
      $query = $this->database->select($fieldTableName, 'ref_field')
        ->fields('ref_field', [$ref_value_column])
        ->condition($ref_value_column, $episodeIDs, 'IN');

      // If we try to add the episode to a new course we cannot filter out the
      // current id because it doesn't have at this point.
      if (!$entity->isNew()) {
        $query->condition('entity_id', $entity->id(), '<>');
      }

      $episodes = $query->execute()->fetchCol();
    }

    if (!empty($episodes)) {
      $entities = $items->referencedEntities();
      $labels = '';
      foreach ($entities as $refEntity) {
        if ($refEntity->bundle() === 'podcast_episode' && in_array($refEntity->id(), $episodes)) {
          if (empty($labels)) {
            $labels = $refEntity->label();
          }
          else {
            $labels = sprintf('%s, %s', $labels, $refEntity->label());
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
