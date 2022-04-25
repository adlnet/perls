<?php

namespace Drupal\perls_content_management\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a flag to an user.
 *
 * @Action(
 *   id = "flag_recommendation_weight",
 *   label = @Translation("Recommend (Details for each node)"),
 *   type = "node"
 * )
 */
class AddRecommendedContentFlag extends ViewsBulkOperationsActionBase implements PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * The name of the flag which has weight option.
   *
   * @var string
   */
  protected $flagName = 'recommendation';

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorage
   */
  protected $userStorage;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * AddRecommendedContentFlag constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FlagServiceInterface $flag_service,
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->flagService = $flag_service;
    $this->entityManager = $entity_type_manager;
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->messenger = $messenger;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flag'),
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $account->hasPermission('administer flaggings');
  }

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL) {
    // This only available for page with user/%user url.
    if ($flagging = $this->loadFlagging($node)) {
      $this->updateFlag($flagging);
    }
    else {
      $flagging = $this->saveFlag($node);
      if (!$flagging) {
        $this->messenger->addMessage($this->t('Something went wrong the system did not add recommended content.'), MessengerInterface::TYPE_ERROR);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $item_counter = 0;
    if ($this->selectAllChecked()) {
      return $this->getSimpleConfigForm($form);
    }

    foreach ($this->context['list'] as $key => $item_data) {
      $entity_type = $item_data[2];
      $entity_id = $item_data[3];
      $storage = $this->entityManager->getStorage($entity_type);
      $content = $storage->load($entity_id);
      $parsed_flagging = NULL;
      if (empty($form_state->getUserInput())) {
        $flagging = $this->loadFlagging($content);
        if ($flagging) {
          $parsed_flagging = $this->parseFlagging($flagging);
        }
      }

      $form['flag_details_' . $item_counter] = [
        '#type' => 'details',
        '#title' => $this->t('Recommended content %content', ['%content' => $content->label()]),
        '#open' => TRUE,
      ];

      $form['flag_details_' . $item_counter]['flag_weight_' . $item_counter] = [
        '#type' => 'select',
        '#title' => $this->t('Weight'),
        '#options' => range(0, 20),
        '#description' => $this->t('You can assign weight to the recommended content. Minimum weight is 0.'),
        '#required' => TRUE,
        '#default_value' => isset($parsed_flagging['weight']) ? $parsed_flagging['weight'] : '',
      ];

      $form['flag_details_' . $item_counter]['flag_weight_details_' . $item_counter] = [
        '#type' => 'textarea',
        '#maxlength' => 250,
        '#title' => $this->t('Recommendation reason'),
        '#description' => $this->t('You can explain here why you recommend this content.'),
        '#default_value' => isset($parsed_flagging['reason']) ? $parsed_flagging['reason'] : '',
      ];
      $item_counter++;
    }

    return $form;
  }

  /**
   * Determines whether the select all option was checked.
   *
   * @return bool
   *   True if "Select all" option was checked, false otherwise.
   */
  protected function selectAllChecked(): bool {
    $selectAll = FALSE;
    if (empty($this->context['list'])) {
      $selectAll = TRUE;
    }
    elseif ($this->context['exclude_mode']) {
      // If select all is checked in the exclude mode, the sum of the number of
      // selected items and number of the items in the context list will be
      // same as the number of total view results.
      if ($this->context['total_results'] == count($this->context['list']) + $this->context['selected_count']) {
        $selectAll = TRUE;
      }
    }
    return $selectAll;
  }

  /**
   * Get a simple configuration form.
   *
   * @param array $form
   *   Initial form array.
   *
   * @return array
   *   The form array.
   */
  protected function getSimpleConfigForm(array $form): array {
    // If Context list is empty, generate from view results.
    $form['flag'] = [
      '#type' => 'details',
      '#title' => $this->t('Recommended content.'),
      '#open' => TRUE,
    ];
    $form['flag']['flag_weight'] = [
      '#type' => 'select',
      '#title' => $this->t('Weight'),
      '#options' => range(0, 20),
      '#description' => $this->t('You can assign weight to the recommended content. Minimum weight is 0.'),
      '#required' => TRUE,
    ];
    $form['flag']['flag_weight_details'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Recommendation reason'),
      '#description' => $this->t('You can explain here why you recommend this content.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['flag_weights'] = [];
    $counter = 0;

    // The flag_weight field will have a value only when select all option
    // would have been checked.
    if ($form_state->hasValue('flag_weight')) {
      $form_values = $form_state->getValues();
      $this->configuration['flag_weights'] = [];
      $this->configuration['flag_weights'][0]['weight'] = $form_values['flag_weight'];
      $this->configuration['flag_weights'][0]['details'] = $form_values['flag_weight_details'];
    }
    else {
      foreach ($form['list']['#items'] as $key => $item) {
        $this->configuration['flag_weights'][$counter]['weight'] = $form_state->getValue('flag_weight_' . $counter);
        $this->configuration['flag_weights'][$counter]['details'] = $form_state->getValue('flag_weight_details_' . $counter);
        $counter++;
      }
    }
  }

  /**
   * Create a new flagging entity with proper fields.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The drupal flag object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object, this entty will be flagged.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account who has the flag.
   *
   * @return object
   *   The new flagging object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function saveNewFlag(FlagInterface $flag, EntityInterface $entity, AccountInterface $account) {
    $flagging = $this->flagService->flag($flag, $entity, $account);
    // Don't use array shift for simple form since it has only one element.
    $weight_details = count($this->configuration['flag_weights']) == 1 ?
      reset($this->configuration['flag_weights']) :
      array_shift($this->configuration['flag_weights']);
    $flagging->set('field_recommendation_score', $weight_details['weight']);
    $flagging->set('field_recommendation_reason', $weight_details['details']);
    $flagging->save();
    return $flagging;
  }

  /**
   * Load a flagging object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A drupal entity.
   *
   * @return mixed
   *   The flagging object otherwise FALSE.
   */
  private function loadFlagging(EntityInterface $entity) {
    if ($user_id = $this->context['arguments'][0]) {
      $flag = $this->flagService->getFlagById($this->flagName);
      $account = $this->userStorage->load($user_id);
      if ($flagging = $this->flagService->getFlagging($flag, $entity, $account)) {
        return $flagging;
      }
    }

    return FALSE;
  }

  /**
   * Parse an existing flagging object to form.
   *
   * @param \Drupal\flag\FlaggingInterface $flagging
   *   A flagging object.
   *
   * @return array
   *   Contains two fields weight and reason values.
   */
  private function parseFlagging(FlaggingInterface $flagging) {
    $parsed_content = [];
    if ($flagging->hasField('field_recommendation_score')) {
      $parsed_content['weight'] = $flagging->get('field_recommendation_score')->getString();
    }
    if ($flagging->hasField('field_recommendation_reason')) {
      $parsed_content['reason'] = $flagging->get('field_recommendation_reason')->getString();
    }

    return $parsed_content;
  }

  /**
   * Save a new flagging object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The drupal content.
   *
   * @return mixed
   *   Flagging object otherwise FALSE.
   */
  private function saveFlag(EntityInterface $entity) {
    if ($user_id = $this->context['arguments'][0]) {
      $flag = $this->flagService->getFlagById($this->flagName);
      $account = $this->userStorage->load($user_id);
      return $this->saveNewFlag($flag, $entity, $account);
    }

    return FALSE;
  }

  /**
   * Update an existing recommendation reason.
   *
   * @param \Drupal\flag\FlaggingInterface $flagging
   *   A flagging object.
   */
  private function updateFlag(FlaggingInterface $flagging) {
    // Don't use array shift for simple form since it has only one element.
    $weight_details = count($this->configuration['flag_weights']) == 1 ?
      reset($this->configuration['flag_weights']) :
      array_shift($this->configuration['flag_weights']);
    if ($weight_details) {
      $flagging->set('field_recommendation_score', $weight_details['weight']);
      $flagging->set('field_recommendation_reason', $weight_details['details']);
      $flagging->save();
    }
  }

}
