<?php

namespace Drupal\perls_content_management\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Uses course(s) to filter learning content belonging to a course(s).
 *
 * @ViewsFilter("related_learning_content")
 */
class RelatedLearningContent extends InOperator {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * User name filter.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager
  ) {
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $label = ($this->options['expose']['label']) ?? $this->t('Course');
    $form['value'] = [
      '#type' => 'select2',
      '#title' => $this->t('Related Learning Content'),
      '#autocomplete' => TRUE,
      '#multiple' => isset($this->options['expose']['multiple']) ? $this->options['expose']['multiple'] : FALSE,
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['course'],
      ],
      '#select2' => [
        'placeholder' => '- ' . $this->t('Any') . ' -',
      ],
      '#attributes' => [
        'data-min-input-text' => $this->t('Type to filter by @label', ['@label' => $label]),
        'data-no-results-text' => $this->t('@label not found', ['@label' => $label]),
      ],
    ];

    $user_input = $form_state->getUserInput();
    if ($form_state->get('exposed')
    && !isset($user_input[$this->options['expose']['identifier']])) {
      $form_state->setUserInput($user_input);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    $accepted = parent::acceptExposedInput($input);

    if ($accepted) {
      // If we have previously validated input, override.
      if (isset($this->validated_exposed_input)) {
        $this->value = $this->validated_exposed_input;
      }
    }

    return $accepted;
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    // If this is not an exposed filter then return.
    if (empty($this->options['exposed'])) {
      return;
    }
    // If the exposed filter does not have an identifier then return.
    if (empty($this->options['expose']['identifier'])) {
      return;
    }

    $identifier = $this->options['expose']['identifier'];

    $ids = [];
    $values = $form_state->getValue($identifier);
    // If the filter has values loop through them and add it to the ID array.
    if ($values) {
      foreach ($values as $value) {
        $ids[] = $value['target_id'];
      }
    }
    // If the ID array is not empty, set this as the validated exposed input.
    if ($ids) {
      $this->validated_exposed_input = $ids;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if ($this->value) {
      $table = $this->ensureMyTable();
      $courses = $this->entityTypeManager->getStorage('node')
        ->loadMultiple($this->value);
      $content = [];
      foreach ($courses as $course) {
        // Combine course content ids from multiple courses.
        $content = array_merge(array_column($course->field_learning_content->getValue(), 'target_id'), $content);
      }

      $this->query->addWhere('AND', $table . '.' . $this->realField, $content, 'IN');
    }
  }

}
