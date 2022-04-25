<?php

namespace Drupal\perls_content_management\Plugin\views\argument_validator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument_validator\Entity;

/**
 * Validate whether an argument is numeric or not.
 *
 * @ingroup views_argument_validate_plugins
 *
 * @ViewsArgumentValidator(
 *   id = "content_type_route_argument",
 *   title = @Translation("Url argument: Content type"),
 *   entity_type = "node_type"
 * )
 */
class ContentTypeValidator extends Entity {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['content_types'] = ['default' => []];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types'),
      '#options' => array_map(
        ['\Drupal\Component\Utility\Html', 'escape'],
        node_type_get_names()
      ),
      '#default_value' => $this->options['content_types'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state, &$options = []) {
    parent::submitOptionsForm($form, $form_state, $options);
    $options['content_types'] = array_filter($options['content_types']);
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($arg) {
    // If the result of parent function is FALSE it's make sense go forward.
    if (!parent::validateArgument($arg)) {
      return FALSE;
    }
    return array_key_exists($arg, $this->options['content_types']);
  }

}
