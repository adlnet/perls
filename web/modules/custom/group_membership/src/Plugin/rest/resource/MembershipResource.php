<?php

namespace Drupal\group_membership\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a resource to manage the current user's group membership.
 *
 * @RestResource(
 *   id = "membership_resource",
 *   label = @Translation("Group membership"),
 *   uri_paths = {
 *     "canonical" = "/group/{group}/membership",
 *     "create" = "/group/{group}/membership",
 *   }
 * )
 */
class MembershipResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Current request route;.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('group_membership');
    $instance->currentUser = $container->get('current_user');
    $instance->routeMatch = $container->get('current_route_match');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Retrieves a user's membership.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The group membership, if found.
   */
  public function get() {
    $group = $this->getGroupFromRoute();

    if (!$group) {
      throw new NotFoundHttpException('Unable to find group');
    }

    $membership = $group->getMember($this->currentUser);
    if (!$membership) {
      throw new NotFoundHttpException('You are not a member of ' . $group->label());
    }

    $response = new ResourceResponse($membership->getGroupContent());
    $response->addCacheableDependency($membership);
    $response->getCacheableMetadata()->addCacheContexts(['user']);
    return $response;
  }

  /**
   * Adds the user to the group specified in the URL.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The newly created membership.
   */
  public function post() {
    $group = $this->getGroupFromRoute();

    if (!$group) {
      throw new NotFoundHttpException('Unable to find group');
    }

    $membership = $group->getMember($this->currentUser);
    if ($membership) {
      throw new ConflictHttpException('You are already a member of ' . $group->label());
    }

    if (!$group->access('join group', $this->currentUser)) {
      throw new AccessDeniedHttpException('You are not allowed to join ' . $group->label());
    }

    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $group->addMember($user);

    $membership = $group->getMember($this->currentUser);
    if (!$membership) {
      return new ModifiedResourceResponse(NULL, 204);
    }

    return new ModifiedResourceResponse($membership->getGroupContent(), 201);
  }

  /**
   * Removes the user from the group specified in the URL.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   An empty response.
   */
  public function delete() {
    $group = $this->getGroupFromRoute();

    if (!$group) {
      throw new NotFoundHttpException('Unable to find group');
    }

    $membership = $group->getMember($this->currentUser);
    if (!$membership) {
      throw new NotFoundHttpException('You are not a member of ' . $group->label());
    }

    if (!$group->access('leave group', $this->currentUser)) {
      throw new AccessDeniedHttpException('You are not allowed to leave ' . $group->label());
    }

    $membership->getGroupContent()->delete();
    return new ModifiedResourceResponse(NULL, 204);
  }

  /**
   * Retrieves the Group referenced by the URL.
   *
   * Verifies the current user has access to view the group.
   *
   * @return \Drupal\group\Entity\GroupInterface
   *   The group referenced in the URL.
   */
  protected function getGroupFromRoute() {
    $group_id = $this->routeMatch->getParameter('group');
    $group = $this->entityTypeManager->getStorage('group')->load($group_id);
    if (!$group->access('view group', $this->currentUser)) {
      return NULL;
    }

    return $group;
  }

}
