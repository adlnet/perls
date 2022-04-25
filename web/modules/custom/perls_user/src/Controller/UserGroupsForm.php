<?php

namespace Drupal\perls_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * User account form related controllers.
 */
class UserGroupsForm extends ControllerBase {

  /**
   * UserGroupsForm constructor.
   *
   * @param \Drupal\Core\Session\AccountProxy $account
   *   A drupal account.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   Entity form builder widget service.
   */
  public function __construct(AccountProxy $account, EntityFormBuilderInterface $entity_form_builder) {
    $this->currentUser = $account;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * Controller of /user/groups path.
   */
  public function getForm() {
    $user = User::load($this->currentUser->id());
    return [
      'form' => $this->entityFormBuilder->getForm($user, 'groups'),
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];
  }

}
