<?php

namespace Drupal\entity_access_condition\Plugin\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Condition\ConditionPluginBase;

/**
 * Provides a basis for checking entity access from context.
 */
abstract class EntityAccessBase extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'entity_operation' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['entity_operation'] = [
      '#type' => 'select',
      '#title' => $this->t('Operation'),
      '#description' => $this->t('Validate that the current user may perform this operation on the entity from the current context.'),
      '#options' => [
        'none' => $this->t("Don't check access"),
        'view' => $this->t('View access'),
        'update' => $this->t('Update access'),
        'delete' => $this->t('Delete access'),
      ],
      '#default_value' => $this->configuration['entity_operation'],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $operation = $form_state->getValue('entity_operation');
    $this->configuration['entity_operation'] = ($operation === 'none') ? NULL : $operation;
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Retrieves the entity from the current context to use for access checking.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity.
   */
  abstract protected function getEntity(): ?EntityInterface;

  /**
   * Evaluates the condition and returns TRUE or FALSE accordingly.
   *
   * @return bool
   *   TRUE if the condition has been met, FALSE otherwise.
   */
  public function evaluate() {
    if (empty($this->configuration['entity_operation'])) {
      return TRUE;
    }

    $entity = $this->getEntity();
    if (!$entity) {
      return FALSE;
    }

    return $entity->access($this->configuration['entity_operation']);
  }

  /**
   * Provides a human readable summary of the condition's configuration.
   */
  public function summary() {
    return $this->t("User has access to '@operation' entity from current context", [
      '@operation' => $this->configuration['entity_operation'],
    ]);
  }

}
