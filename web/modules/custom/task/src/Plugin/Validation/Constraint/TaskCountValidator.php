<?php

namespace Drupal\task\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * TaskCountValidator class that does.
 */
class TaskCountValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new GroupContentUninstallValidator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!$constraint instanceof TaskCountConstraint) {
      throw new UnexpectedTypeException($constraint, TaskCountConstraint::class);
    }
    // If there is no value to validate or we are updating an existint task
    // entity do not evaluate task count contstraint.
    if (NULL === $value || $value->id() != NULL) {
      return;
    }

    $entityStorage = $this->entityTypeManager->getStorage('task');
    $query = $entityStorage->getQuery();
    $query->condition('type', $value->type->entity->id());
    $query->condition('user_id', $value->getOwnerId());
    $query->notExists('completion_date');
    $taskIds = $query->execute();

    if (count($taskIds) >= 10) {
      $this->context->buildViolation($constraint->maxMessage)
        ->setParameter('{{ count }}', 10)
        ->setParameter('{{ limit }}', 10)
        ->setInvalidValue($value)
        ->setCode(Count::TOO_MANY_ERROR)
        ->addViolation();
    }

  }

}
