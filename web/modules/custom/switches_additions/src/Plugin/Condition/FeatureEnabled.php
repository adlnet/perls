<?php

namespace Drupal\switches_additions\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Feature enabled' condition.
 *
 * @Condition(
 *   id = "feature_enabled",
 *   label = @Translation("Feature enabled")
 * )
 */
class FeatureEnabled extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * FeatureEnabled constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['switches'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('When the site has the following features enabled'),
      '#default_value' => $this->configuration['switches'],
      '#options' => $this->getSwitchList(),
      '#description' => $this->t('If you select no feature, the condition will evaluate to TRUE.'),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['switches'] = array_filter($form_state->getValue('switches'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'switches' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if (empty($this->configuration['switches']) && !$this->isNegated()) {
      return TRUE;
    }

    return (bool) array_intersect($this->configuration['switches'], $this->getEnabledFeatures());
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    // Use the role labels. They will be sanitized below.
    $features_list = array_intersect_key($this->getSwitchList(), $this->configuration['switches']);
    if (count($features_list) > 1) {
      $features = implode(', ', $features_list);
    }
    else {
      $features = reset($features_list);
    }
    if (!empty($this->configuration['negate'])) {
      return $this->formatPlural(count($features_list), '@features feature is not enabled', '@features features are not enabled', [
        '@features' => $features,
      ]);
    }
    else {
      return $this->formatPlural(count($features_list), '@features feature is enabled', '@features features are enabled', [
        '@features' => $features,
      ]);
    }
  }

  /**
   * Provide a list of available switch entity in the system.
   *
   * @return array
   *   A list of switch where the key is the id the value is the label.
   */
  protected function getSwitchList():array {
    $switch_list = [];
    $switches = $this->entityTypeManager->getStorage('switch')->loadMultiple();
    foreach ($switches as $switch) {
      $switch_list[$switch->id()] = $switch->label();
    }

    return $switch_list;
  }

  /**
   * Gives back the enabled feature in the site.
   *
   * @return array
   *   A list of switch which is active on site.(ids)
   */
  protected function getEnabledFeatures():array {
    $enabled_features = [];
    $switches = $this->entityTypeManager->getStorage('switch')->loadMultiple();
    /** @var \Drupal\switches\Entity\SwitchEntity $switch */
    foreach ($switches as $switch) {
      if ($switch->getActivationStatus()) {
        $enabled_features[] = $switch->id();
      }
    }

    return $enabled_features;
  }

}
