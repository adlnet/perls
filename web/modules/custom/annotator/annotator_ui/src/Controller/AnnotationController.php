<?php

namespace Drupal\annotator_ui\Controller;

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a event subscriber to react learn_link node view.
 */
class AnnotationController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  private const BLOCK_NAME = "annotations_vue_block";

  /**
   * AnnotationController constructor.
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
   * Renders VueJS block to show list of annotations.
   */
  public function view() {
    $block = Block::load(self::BLOCK_NAME);
    if (empty($block)) {
      throw new \Exception('A block must be defined');
    }

    $block_content = $this->entityTypeManager
      ->getViewBuilder('block')
      ->view($block);

    return [
      '#type' => 'container',
      'element-content' => $block_content,
    ];
  }

}
