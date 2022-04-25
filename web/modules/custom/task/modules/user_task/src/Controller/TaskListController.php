<?php

namespace Drupal\user_task\Controller;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a event subscriber to react learn_link node view.
 */
class TaskListController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  private const BLOCK_NAME = "user_task_list_vue_block";

  /**
   * TaskListController constructor.
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
   * Renders VueJS block to show list of tasks.
   *
   * @throws \Exception
   */
  public function view(): array {
    $storage = $this->entityTypeManager->getStorage('block');
    $block = $storage->load(self::BLOCK_NAME);
    if (empty($block)) {
      throw new \Exception('A block must be defined');
    }

    $builder = $this->entityTypeManager->getViewBuilder('block');
    $block_content = $builder->view($block);

    return [
      '#type' => 'container',
      'element-content' => $block_content,
      '#cache' => [
        'contexts' => ['session'],
      ],
    ];
  }

  /**
   * Check the access to user task list.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The active Drupal user.
   * @param int $user
   *   The uid of the user of the task list to view.
   *
   * @return \Drupal\Core\Access\AccessResultReasonInterface
   *   The AccessResult for this page.
   */
  public function access(AccountInterface $account, int $user = NULL) {
    if ((int) $account->id() === $user) {
      return AccessResultAllowed::allowedIfHasPermission($account, 'view own user_task task');
    }
    else {
      return AccessResultAllowed::allowedIfHasPermission($account, 'view any user_task task');
    }
  }

}
