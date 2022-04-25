<?php

namespace Drupal\content_moderation_additions\Access;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks access for displaying configuration translation page.
 */
class DiscussionAccessCheck implements AccessInterface {

  /**
   * Config for content moderation.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  private $moderationInformation;

  /**
   * CustomAccessCheck constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   */
  public function __construct(ConfigFactoryInterface $config, ModerationInformationInterface $moderation_information) {
    $this->config = $config->get('content_moderation_additions.settings');
    $this->moderationInformation = $moderation_information;
  }

  /**
   * A custom access check for discussions page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   The route match object for this route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, RouteMatch $route_match) {
    // We need to check three things here:
    // 1. The user can post comments.
    // 2. The moderation comments are enabled.
    // 3. The entity being accessed allows moderation.
    $uses_moderation = FALSE;
    if ($node = $route_match->getParameter('node')) {
      $uses_moderation = $this->moderationInformation->isModeratedEntity($node);
    }

    return (
        $account->hasPermission('post comments')
        && $this->config->get('enable_moderation_comments')
        && $uses_moderation) ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
