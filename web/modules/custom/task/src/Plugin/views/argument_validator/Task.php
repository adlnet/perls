<?php

namespace Drupal\task\Plugin\views\argument_validator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views\Plugin\views\argument_validator\Entity;

/**
 * Defines an argument validator plugin for each entity type.
 *
 * @ViewsArgumentValidator(
 *   id = "task",
 *   deriver = "Drupal\task\Plugin\Derivative\ViewsTaskArgumentValidator"
 * )
 *
 * @see \Drupal\task\Plugin\Derivative\ViewsTaskArgumentValidator
 */
class Task extends Entity {

  /**
   * If this validator can handle multiple arguments.
   *
   * @var bool
   */
  protected $multipleCapable = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    unset($options["multiple"]);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $entity_type_id = $this->definition['entity_type'];
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

    // Derivative IDs are all entity:entity_type. Sanitized for js.
    // The ID is converted back on submission.
    $sanitized_id = ArgumentPluginBase::encodeValidatorId($this->definition['id']);

    // If the entity has bundles, allow option to restrict to bundle(s).
    if ($entity_type->hasKey('bundle')) {
      $bundle_options = [];
      foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle_id => $bundle_info) {
        $bundle_options[$bundle_id] = $bundle_info['label'];
      }

      $form['bundles'] = [
        '#title' => $entity_type->getBundleLabel() ?: $this->t('Bundles'),
        '#default_value' => $this->options['bundles'],
        '#type' => 'checkboxes',
        '#options' => $bundle_options,
        '#description' => $this->t('If none are selected, all are allowed.'),
      ];
    }

    // Offer the option to filter by access to the entity in the argument.
    $form['access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate user ID has access to the task'),
      '#default_value' => $this->options['access'],
    ];
    $form['operation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Access operation to check'),
      '#options' => [
        'view' => $this->t('View'),
        'update' => $this->t('Edit'),
      ],
      '#default_value' => $this->options['operation'],
      '#states' => [
        'visible' => [
          ':input[name="options[validate][options][' . $sanitized_id . '][access]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($argument) {
    if ($argument) {
      $ids = [$argument];
    }
    // No specified argument should be invalid.
    else {
      return FALSE;
    }

    $entity_type = $this->definition['entity_type'];

    $entities = $this->entityTypeManager->getStorage($entity_type)->loadByProperties([
      'user_id' => reset($ids),
    ]);

    if (empty($entities)) {
      return FALSE;
    }

    // Validate each entity. If any fails break out and return false.
    foreach ($entities as $entity) {
      if (!$this->validateEntity($entity)) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
