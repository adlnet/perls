<?php

namespace Drupal\perls_content_management;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper class to manage access of the links on course page.
 */
class CoursePageAccess implements ContainerInjectionInterface {

  /**
   * Drupal node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Current logged in drupal user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Help to manage access of course tabs.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Drupal entity type manager.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   Current drupal user.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxy $current_user) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * Allow the show stat pages if the parent is a course page.
   */
  public function statPageAccess($node) {
    if (is_numeric($node)) {
      /** @var \Drupal\node\Entity\Node $node_object */
      $node_object = $this->nodeStorage->load($node);
      if ($node_object && $node_object->bundle() === 'course' && $this->currentUser->hasPermission('access course stat')) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

}
