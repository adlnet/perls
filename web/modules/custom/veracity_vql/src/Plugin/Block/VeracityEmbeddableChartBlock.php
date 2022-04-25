<?php

namespace Drupal\veracity_vql\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'VeracityEmbeddableChartBlock' block.
 *
 * @Block(
 *  id = "veracity_embeddable_chart_block",
 *  admin_label = @Translation("Embeddable Chart"),
 * )
 */
class VeracityEmbeddableChartBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\veracity_vql\VeracityApiInterface definition.
   *
   * @var \Drupal\veracity_vql\VeracityApiInterface
   */
  protected $veracityApi;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->veracityApi = $container->get('veracity_vql.api');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'chart_id' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['chart_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chart ID'),
      '#required' => TRUE,
      '#description' => $this->t('The chart ID to embed.'),
      '#placeholder' => 'a5d2d451-fd81-4047-9035-ab3662d8f12e',
      '#default_value' => $this->configuration['chart_id'],
      '#maxlength' => 36,
      '#size' => 40,
      '#weight' => '0',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['chart_id'] = $form_state->getValue('chart_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $chart_url = $this->getFrameUrl();

    if (!$chart_url) {
      return [];
    }

    $build['container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'veracity-result-render embedded',
      ],
    ];

    $build['container']['chart'] = [
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
        'src' => $chart_url,
        'style' => 'width: 100%; height: 100%;',
      ],
    ];

    $build['#cache']['max-age'] = 3600;

    return $build;
  }

  /**
   * Determines the URL for loading the chart.
   *
   * @return string|null
   *   The URL for the chart.
   */
  protected function getFrameUrl(): ?string {
    $id = $this->configuration['chart_id'];
    $host = parse_url($this->veracityApi->getEndpoint(), PHP_URL_HOST);
    return "https://$host/integrations/embedableCharts/?id=$id";
  }

}
