<?php

namespace Drupal\perls_content_management\Plugin\views\field;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a field that calculate the diff between this field and other field.
 *
 * @ViewsField("flagging_date_diff")
 */
class FlaggingDateDiff extends FieldPluginBase {

  /**
   * String translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translation;

  /**
   * Constructs a FlaggingDateDiff object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   Drupal translation service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TranslationInterface $translation) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->translation = $translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['other_field'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $previous_labels = $this->getPreviousFieldLabels();
    $field_list = [];
    foreach ($previous_labels as $field_id => $field_label) {
      $field_list[$field_id] = preg_replace("/\([^)]+\)/", "", $field_label);
    }
    $form['other_field'] = [
      '#title' => $this->t('Other field'),
      '#description' => $this->t('Please select other fields which will be subtract from this field'),
      '#type' => 'select',
      '#options' => $field_list,
      '#default_value' => $this->options['other_field'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $end_date = $this->getValue($values);
    if (!empty($end_date)) {
      $end_date = DrupalDateTime::createFromTimestamp($end_date);
      /** @var \Drupal\views\Plugin\views\field\EntityField $start_field_object */
      $start_field_object = $this->view->field[$this->options['other_field']];
      $start_field_options = $start_field_object->options;
      if ($start_field_options['type'] === 'timestamp') {
        $start_field_format = $start_field_options['settings']['date_format'];
        $date_format = DateFormat::load($start_field_format);
        $rendered_date = (string) $start_field_object->last_render_text;
        $start_date = DrupalDateTime::createFromFormat($date_format->getPattern(), $rendered_date);
        $days = (int) $end_date->diff($start_date)->format('%a') ?: 1;
        return $this->translation->formatPlural($days, '1 day', '@count days');
      }
    }
  }

}
