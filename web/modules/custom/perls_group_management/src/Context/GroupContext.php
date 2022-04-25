<?php

namespace Drupal\perls_group_management\Context;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\perls_group_management\GroupViewsBulkOperationsTrait;

/**
 * Sets the current group as a context for VBO actions.
 */
class GroupContext implements ContextProviderInterface {
  use GroupViewsBulkOperationsTrait;

  /**
   * The original group context service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextProviderInterface
   */
  protected $innerService;

  /**
   * The current route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Constructs a new group context which can yield a context from VBO.
   *
   * @param \Drupal\Core\Plugin\Context\ContextProviderInterface $inner_service
   *   The original group context service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match object.
   */
  public function __construct(ContextProviderInterface $inner_service, RouteMatchInterface $current_route_match) {
    $this->innerService = $inner_service;
    $this->currentRouteMatch = $current_route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $contexts = $this->innerService->getRuntimeContexts($unqualified_context_ids);
    $route_object = $this->currentRouteMatch->getRouteObject();
    if ($route_object && $route_object->hasRequirement('_views_bulk_operation_access')) {
      $data = $this->getCurrentViewsBulkOperationContext();
      $group = $this->getGroupFromViewsBulkOperationContext($data);

      $context = new Context($contexts['group']->getContextDefinition(), $group);
      return ['group' => $context];
    }

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return $this->innerService->getAvailableContexts();
  }

}
