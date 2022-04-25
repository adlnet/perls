<?php

namespace Drupal\veracity_vql_additions\Plugin\VqlPreProcess;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\veracity_vql\Plugin\VqlPreProcess\ContextActivityFilter;

/**
 * Filters statement context by a node.
 *
 * @VqlPreProcess(
 *   id = "filter_by_context_node",
 *   label = "Filter Context by Content",
 *   description = "Filters statement context by the current node context.",
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Content")),
 *   }
 * )
 */
class ContentContextFilter extends ContextActivityFilter implements ContextAwarePluginInterface, ContainerFactoryPluginInterface {

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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    unset($form['activity_id']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getActivityId(): ?string {
    $activity = $this->activityProvider->getActivity($this->getContextValue('node'));
    return $activity['id'];
  }

}
