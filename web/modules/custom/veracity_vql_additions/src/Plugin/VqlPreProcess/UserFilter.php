<?php

namespace Drupal\veracity_vql_additions\Plugin\VqlPreProcess;

use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\veracity_vql\Plugin\VqlPreProcess\FilterBase;

/**
 * Filters results by a user.
 *
 * @VqlPreProcess(
 *   id = "filter_by_user",
 *   label = "Filter by User",
 *   description = "Filters results by the current user context.",
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user", label = @Translation("User")),
 *   }
 * )
 */
class UserFilter extends FilterBase implements ContextAwarePluginInterface, ContainerFactoryPluginInterface {

  use ContextAwarePluginTrait;

  /**
   * The current IFI manager.
   *
   * @var \Drupal\xapi\XapiActorIFIManager
   */
  protected $ifiManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->ifiManager = $container->get('plugin.manager.xapi_actor_ifi');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilter(): array {
    $filter['actor'] = $this->ifiManager->getActiveIfi($this->getContextValue('user'));
    return $filter;
  }

}
