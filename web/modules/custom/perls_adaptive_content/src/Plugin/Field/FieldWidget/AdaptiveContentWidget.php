<?php

namespace Drupal\perls_adaptive_content\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\perls_adaptive_content\AdaptiveContentServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'adaptive_content_widget' widget.
 *
 * @FieldWidget(
 *   id = "adaptive_content_widget",
 *   label = @Translation("Adaptive Content Widget"),
 *   field_types = {
 *     "adaptive_content_field"
 *   }
 * )
 */
class AdaptiveContentWidget extends WidgetBase {
  /**
   * Adaptive Content Service.
   *
   * @var \Drupal\perls_adaptive_content\AdaptiveContentServiceInterface
   */
  protected $adaptiveContentService;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\perls_adaptive_content\AdaptiveContentServiceInterface $adaptive_service
   *   The adaptive content service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    AdaptiveContentServiceInterface $adaptive_service) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->adaptiveContentService = $adaptive_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('perls_adaptive_content.adaptive_content_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'display' => 'select',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['display'] = [
      '#type' => 'select',
      '#title' => t('Display As'),
      '#options' =>
        [
          'select' => $this->t('Select Menu'),
        ],
      '#default_value' => $this->getSetting('display'),
      '#required' => TRUE,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = t('Display as: @display', ['@display' => $this->getSetting('display')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Get all plugins:
    $plugins = $this->adaptiveContentService->getAdaptiveContentPlugins();
    $options = [
      '_none' => $this->t('Disable Adaptive Learning'),
    ];
    foreach ($plugins as $id => $plugin) {
      $options[$id] = $plugin->label();
    }
    switch ($this->getSetting('display')) {
      case 'select':
        $element['value'] = $element + [
          '#type' => 'select',
          '#options' => $options,
          '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
          '#attributes' => ['class' => ['adaptive_content_select']],
        ];
        break;
    }

    return $element;
  }

}
