<?php

namespace Drupal\veracity_vql_additions\Plugin\VqlPreProcess;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\veracity_vql\Plugin\VqlPreProcess\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters results by a node.
 *
 * @VqlPreProcess(
 *   id = "filter_by_node",
 *   label = "Filter by Content",
 *   description = "Filters results by the current node context.",
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Content")),
 *   }
 * )
 */
class ContentFilter extends FilterBase implements ContextAwarePluginInterface, ContainerFactoryPluginInterface {

  use ContextAwarePluginTrait;

  /**
   * The current activity provider.
   *
   * @var \Drupal\xapi\XapiActivityProviderInterface
   */
  protected $activityProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->activityProvider = $container->get('xapi.activity_provider');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilter(): array {
    $activity = $this->activityProvider->getActivity($this->getContextValue('node'));
    $filter['object']['id'] = $activity['id'];
    return $filter;
  }

}
