<?php

namespace Drupal\perls_content_management\Plugin\views\access;

use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\Routing\Route;

/**
 * Register an access function for course pages.
 *
 * @ViewsAccess(
 *   id = "only_course_page_access",
 *   title = @Translation("Only show on course page."),
 *   help = @Translation("Access will be granted to only show on course pages.")
 * )
 *
 * @ingroup views_access_plugins
 */
class OnlyCoursePage extends AccessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_custom_access', 'Drupal\perls_content_management\CoursePageAccess::statPageAccess');
  }

}
