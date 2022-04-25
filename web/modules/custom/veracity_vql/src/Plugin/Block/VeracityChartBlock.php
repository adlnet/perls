<?php

namespace Drupal\veracity_vql\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\SubformState;
use Drupal\Component\Utility\Random;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\veracity_vql\VeracityClientException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'VeracityChartBlock' block.
 *
 * @Block(
 *  id = "veracity_chart_block",
 *  admin_label = @Translation("VQL Chart"),
 *  context_definitions = {
 *    "user" = @ContextDefinition("entity:user", label = @Translation("User"), required = FALSE),
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Content"), required = FALSE),
 *  }
 * )
 */
class VeracityChartBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The Veracity API.
   *
   * @var \Drupal\veracity_vql\VeracityApi
   */
  protected $veracityApi;

  /**
   * Plugin manager for post-process plugins.
   *
   * @var \Drupal\veracity_vql\Plugin\VqlPreProcessManager
   */
  protected $vqlPreProcessPluginManager;

  /**
   * Plugin manager for post-process plugins.
   *
   * @var \Drupal\veracity_vql\Plugin\VqlPostProcessManager
   */
  protected $vqlPostProcessPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->veracityApi = $container->get('veracity_vql.api');
    $instance->vqlPreProcessPluginManager = $container->get('plugin.manager.vql_pre_process');
    $instance->vqlPostProcessPluginManager = $container->get('plugin.manager.vql_post_process');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'vql' => NULL,
      'post_process' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['vql'] = [
      '#type' => 'textarea',
      '#required' => TRUE,
      '#title' => $this->t('VQL'),
      '#description' => $this->t('Provide the VQL for rendering a chart'),
      '#default_value' => $this->configuration['vql'],
      '#attributes' => [
        'style' => 'font-family: monospace;',
      ],
    ];

    $form['pre_process'] = [
      '#type' => 'details',
      '#title' => $this->t('Pre-process'),
    ];

    foreach ($this->getPreProcessPlugins(TRUE) as $plugin_id => $plugin) {
      $form['pre_process'][$plugin_id] = [
        '#type' => 'fieldset',
        '#title' => $plugin->getPluginLabel(),
        '#description' => $plugin->getPluginDescription(),
      ];
      $plugin_form_state = SubformState::createForSubform($form['pre_process'][$plugin_id], $form, $form_state);
      $form['pre_process'][$plugin_id] += $plugin->buildConfigurationForm($form['pre_process'][$plugin_id], $plugin_form_state);
    }

    $form['post_process'] = [
      '#type' => 'details',
      '#title' => $this->t('Post-process'),
    ];

    foreach ($this->getPostProcessPlugins(TRUE) as $plugin_id => $plugin) {
      $form['post_process'][$plugin_id] = [
        '#type' => 'fieldset',
        '#title' => $plugin->getPluginLabel(),
        '#description' => $plugin->getPluginDescription(),
      ];
      $plugin_form_state = SubformState::createForSubform($form['post_process'][$plugin_id], $form, $form_state);
      $form['post_process'][$plugin_id] += $plugin->buildConfigurationForm($form['post_process'][$plugin_id], $plugin_form_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $vql = $form_state->getValue('vql');

    try {
      $query = json_decode($vql, TRUE, 512, JSON_THROW_ON_ERROR);

      if (!isset($query['filter']) && !isset($query['process'])) {
        $form_state->setError($form['vql'], $this->t('Expecting to see either a filter or process step in the VQL'));
      }

      $result = $this->veracityApi->executeVql($vql);
      if (!$result) {
        $form_state->setError($form['vql'], $this->t('Veracity returned no result; the VQL is malformed'));
      }

      if (isset($query['title']) && $form_state->getValue('label') == $this->getPluginDefinition()['admin_label']) {
        $form_state->setValue('label', $query['title']);
      }
    }
    catch (\JsonException $e) {
      $form_state->setError($form['vql'], $this->t('VQL must be valid JSON'));
    }
    catch (VeracityClientException $e) {
      $form_state->setError($form['vql'], $this->t('Veracity returned an error: %error', ['%error' => $e->getMessage()]));
    }
    catch (\Exception $e) {
      $form_state->setError($form['vql'], $e->getMessage());
    }

    // @todo Remove when https://www.drupal.org/project/drupal/issues/2948549 is closed.
    $form_state->setTemporaryValue('block_form_parents', $form['#parents']);

    foreach ($this->getPreProcessPlugins(TRUE) as $plugin_id => $plugin) {
      $plugin_form_state = SubformState::createForSubform($form['pre_process'][$plugin_id], $form, $form_state);
      $plugin->validateConfigurationForm($form['pre_process'][$plugin_id], $plugin_form_state);
    }

    foreach ($this->getPostProcessPlugins(TRUE) as $plugin_id => $plugin) {
      $plugin_form_state = SubformState::createForSubform($form['post_process'][$plugin_id], $form, $form_state);
      $plugin->validateConfigurationForm($form['post_process'][$plugin_id], $plugin_form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // @todo Remove when https://www.drupal.org/project/drupal/issues/2948549 is closed.
    $block_form = NestedArray::getValue($form, $form_state->getTemporaryValue('block_form_parents'));
    if (!empty($block_form)) {
      $form = $block_form;
    }

    $this->configuration['vql'] = $form_state->getValue('vql');

    foreach ($this->getPreProcessPlugins(TRUE) as $plugin_id => $plugin) {
      $plugin_form_state = SubformState::createForSubform($form['pre_process'][$plugin_id], $form, $form_state);
      $plugin->submitConfigurationForm($form['pre_process'][$plugin_id], $plugin_form_state);
      $this->configuration['pre_process'][$plugin_id] = $plugin->getConfiguration();
    }

    foreach ($this->getPostProcessPlugins(TRUE) as $plugin_id => $plugin) {
      $plugin_form_state = SubformState::createForSubform($form['post_process'][$plugin_id], $form, $form_state);
      $plugin->submitConfigurationForm($form['post_process'][$plugin_id], $plugin_form_state);
      $this->configuration['post_process'][$plugin_id] = $plugin->getConfiguration();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access veracity charts');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $id = isset($this->configuration['uuid']) ? $this->configuration['uuid'] : (new Random())->name();
    $chart_id = "chart-$id";

    $vql = $this->vqlPreProcessPluginManager->alterQuery($this->configuration['vql'], $this->configuration['pre_process'] ?? [], $this->getContexts());

    try {
      $result = $this->veracityApi->executeVql($vql);
    }
    catch (\Exception $e) {
      $this->messenger()->addWarning($this->t('Unable to render Veracity chart; check log for more details.'));
      return [
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }

    $this->vqlPostProcessPluginManager->processResult($result, $this->configuration['post_process'] ?? [], $this->getContexts());

    // Avoid rendering an empty chart.
    if (static::isChartEmpty($result)) {
      return [
        'placeholder' => [
          '#type' => 'container',
          '#attributes' => ['class' => 'veracity-result-render no-data'],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t("There isn't enough data for this chart yet."),
            '#attributes' => ['class' => 'empty-message'],
          ],
        ],
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }

    $build = [];
    $build['#attached']['library'][] = 'veracity_vql/vql-renderer';
    $build['#attached']['drupalSettings']['veracity_vql'][$chart_id] = [
      'vql' => $result,
      // The theme can be customized by the theme.
      // @see hook_js_settings_alter.
      'theme' => [
        'name' => NULL,
        'url' => NULL,
        'background' => NULL,
      ],
    ];

    $build['chart'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $chart_id,
        'class' => 'veracity-result-render ' . static::getRenderWidgetType($result),
      ],
      '#cache' => [
        'max-age' => 3600,
      ],
    ];

    return $build;
  }

  /**
   * Determines whether a VQL result is empty.
   *
   * @param array $result
   *   The VQL result.
   *
   * @return bool
   *   TRUE if the result is empty.
   */
  protected static function isChartEmpty(array $result): bool {
    if (empty($result)) {
      return TRUE;
    }

    foreach ($result as $chart) {
      if (!empty($chart['data'])) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Determins the type of rendering being used for the VQL result.
   *
   * @param array $result
   *   The VQL result.
   *
   * @return string
   *   The type of rendering output we're expecting (i.e. "chart").
   */
  protected static function getRenderWidgetType(array $result): string {
    $types = array_map(function ($widget) {
      if (isset($widget['chart'])) {
        return 'chart';
      }
      if (isset($widget['widgetType'])) {
        return strtolower($widget['widgetType']);
      }

      return 'unknown';
    }, $result);

    return implode(' ', $types);
  }

  /**
   * Retrieves plugins for altering the VQL query.
   *
   * @param bool $includeDisabled
   *   Whether to include plugins that are disabled by the configuration.
   *
   * @return array
   *   An array of pre-process plugins keyed by plugin ID.
   */
  protected function getPreProcessPlugins(bool $includeDisabled = TRUE): array {
    return $this->vqlPreProcessPluginManager->getPlugins($this->configuration['pre_process'] ?? [], $includeDisabled);
  }

  /**
   * Retrieves plugins for processing and altering the VQL result.
   *
   * @param bool $includeDisabled
   *   Whether to include plugins that are disabled by the configuration.
   *
   * @return array
   *   An array of post-process plugins keyed by plugin ID.
   */
  protected function getPostProcessPlugins(bool $includeDisabled = TRUE): array {
    return $this->vqlPostProcessPluginManager->getPlugins($this->configuration['post_process'] ?? [], $includeDisabled);
  }

}
