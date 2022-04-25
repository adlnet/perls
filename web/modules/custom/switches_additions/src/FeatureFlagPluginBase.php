<?php

namespace Drupal\switches_additions;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Component\Plugin\PluginHelper;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a basis for fulfilling contexts for condition plugins.
 *
 * @see \Drupal\Core\Condition\Annotation\Condition
 * @see \Drupal\Core\Condition\ConditionInterface
 * @see \Drupal\Core\Condition\ConditionManager
 *
 * @ingroup feature_flag_api
 */
abstract class FeatureFlagPluginBase extends PluginBase implements FeatureFlagPluginInterface {
  /**
   * An array of configuration to instantiate the plugin with.
   *
   * @var array
   */
  protected $configuration;

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $plugin = $this->get($this->instanceId);
    if (PluginHelper::isConfigurable($plugin)) {
      return $plugin->getConfiguration();
    }
    else {
      return $this->configuration;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration($configuration) {
    $this->configuration = $configuration;
    $plugin = $this->get($this->instanceId);
    if (PluginHelper::isConfigurable($plugin)) {
      $plugin->setConfiguration($configuration);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Administrative label'),
      '#default_value' => $this->configuration['label'],
    ];
    $form['switch_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Switch id'),
      '#default_value' => $this->configuration['switch_id'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['label'] = $form_state->getValue('label');
    $this->configuration['switch_id'] = $form_state->getValue('switch_id');
  }

  /**
   * The id of the switch to watch.
   *
   * @var string
   */
  public function getSwitchId() {
    return $this->pluginDefinition['switchId'];
  }

  /**
   * Returns the switch entity based on switchId.
   */
  public function getSwitch() {
    $switch_manager = \Drupal::service('switches.manager');
    try {
      return $switch_manager->getSwitch($this->getSwitchId());
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Returns TRUE if switch is disabled.
   */
  public function isSwitchDisabled() {
    $switch = $this->getSwitch();
    if (!isset($switch)) {
      return FALSE;
    }
    return $switch->getActivationStatus() === FALSE;
  }

  /**
   * Triggered when feature is toggled --only manual activation.
   */
  public function featureWasToggled() {
    if (!$this->isSwitchManualActivation()) {
      return;
    }
    if ($this->isSwitchDisabled()) {
      $this->featureWasDisabled();
    }
    else {
      $this->featureWasEnabled();
    }
  }

  /**
   * Convenient way to check switch for manual activation.
   */
  private function isSwitchManualActivation() {
    $switch = $this->getSwitch();
    if (!isset($switch)) {
      return FALSE;
    }
    return $switch->getActivationMethod() === 'manual';
  }

}
