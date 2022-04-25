<?php

namespace Drupal\veracity_vql\Plugin\VqlPostProcess;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\veracity_vql\Plugin\VqlPostProcessBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds an axis range to the chart.
 *
 * @VqlPostProcess(
 *   id = "axis_range",
 *   label = "Axis Range",
 *   description = "Annotates the graph with a range/goal line",
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user", label = @Translation("User"), required = FALSE),
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Content"), required = FALSE),
 *   }
 * )
 */
class AxisRange extends VqlPostProcessBase implements ContextAwarePluginInterface, ContainerFactoryPluginInterface {

  use ContextAwarePluginTrait;

  /**
   * Current token replacement service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->token = $container->get('token');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label' => $this->t('Goal'),
      'color' => '#AAAAAA',
      'value' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration() + $this->defaultConfiguration();

    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#default_value' => $config['value'],
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $config['label'],
    ];
    $form['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['node', 'user'],
    ];
    $form['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#default_value' => $config['color'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function processResult(array &$result) {
    foreach ($result as &$chart) {
      if (!isset($chart['chart'])) {
        continue;
      }

      $label = $this->token->replace($this->configuration['label'], $this->getContextValues());
      $value = $this->token->replace($this->configuration['value'], $this->getContextValues());

      if (!$value) {
        continue;
      }

      $chart['chart']['yAxes'][0]['axisRanges'][] = [
        'value' => $value,
        'grid' => [
          'above' => TRUE,
          'stroke' => $this->configuration['color'],
          'strokeWidth' => 2,
          'strokeOpacity' => 1,
        ],
        'label' => [
          'inside' => TRUE,
          'text' => $label,
          'fill' => $this->configuration['color'],
          'align' => 'right',
          'verticalCenter' => 'bottom',
        ],
      ];
    }

  }

}
