<?php

namespace Drupal\perls_content_management\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class to create multiple user assign VBO action.
 */
class MultipleUserFlaggerBase extends ViewsBulkOperationsActionBase implements PluginFormInterface, ContainerFactoryPluginInterface {

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
   * MultipleUserFlaggerBase constructor.
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FlagServiceInterface $flag_service, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->flagService = $flag_service;
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
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
    $config = array_shift($this->configuration['multiple_flags']);
    foreach ($config['flags'] as $flag_name) {
      $flag = $this->flagService->getFlagById($flag_name);
      $this->flagAction($flag, $node, $config['users']);
    }
  }

  /**
   * Action form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   * @param bool $multiple
   *   Indicates that the form render a form for all content or isn't.
   *
   * @return array
   *   The form structure.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $multiple = TRUE) {
    if ($multiple) {
      foreach ($form['list']['#items'] as $key => $item) {
        $this->formBaseStructure($form, $form_state, $key, $item);
      }
    }
    else {
      $this->formBaseStructure($form, $form_state, 0);
    }

    $form_state->set('isMultiple', $multiple);

    return $form;
  }

  /**
   * Build the action form.
   *
   * @param array $form
   *   The form object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state object.
   * @param string $counter
   *   The element number.
   * @param string $content
   *   The title of a node.
   */
  public function formBaseStructure(array &$form, FormStateInterface $form_state, $counter, $content = NULL) {
    if (isset($content)) {
      $details_message = $this->t('Assign users to all content');
    }
    else {
      $details_message = $this->t('Assign users to content %content', ['%content' => $content]);
    }

    $form['flag_assign_' . $counter] = [
      '#type' => 'details',
      '#title' => $details_message,
      '#open' => TRUE,
    ];

    $form['flag_assign_' . $counter]['flags_' . $counter] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Flags'),
      '#options' => $this->getAllFlags(),
      '#required' => TRUE,
    ];

    $form['flag_assign_' . $counter]['users_' . $counter] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#tags' => TRUE,
      '#title' => $this->t('Users'),
      '#description' => $this->t('You can select multiple user, separated by comma.'),
      '#validate_reference' => FALSE,
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $this->configuration['multiple_flags'] = [];
    if ($form_state->get('isMultiple')) {
      foreach ($form['list']['#items'] as $key => $item) {
        $this->configuration['multiple_flags'][$key]['flags'] = array_filter($form_values['flags_' . $key]);
        $this->configuration['multiple_flags'][$key]['users'] = $form_values['users_' . $key];
      }
    }
    else {
      $this->configuration['multiple_flags']['flags'] = array_filter($form_values['flags_' . 0]);
      $this->configuration['multiple_flags']['users'] = $form_values['users_' . 0];
    }
  }

  /**
   * Create flagging for multiple users.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The drupal flag object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object, this entity will be flagged.
   * @param array $users
   *   List of uid.
   */
  public function flagAction(FlagInterface $flag, EntityInterface $entity, array $users) {
    foreach ($users as $user) {
      $account = $this->userStorage->load($user['target_id']);
      $flagging = $this->flagService->getFlagging($flag, $entity, $account);
      if (isset($flagging)) {
        $this->messenger->addMessage($this->t("This user has this flag on content the %content.", ['%content' => $entity->label()]), MessengerInterface::TYPE_WARNING);
      }
      else {
        $this->flagService->flag($flag, $entity, $account);
      }
    }
  }

  /**
   * Collects all flag on site and it gives back in option compatible format.
   *
   * @return array
   *   The flag list where the key is the flag machine name.
   */
  public function getAllFlags() {
    $flags = $this->flagService->getAllFlags();
    $flags_ids = [];
    foreach ($flags as $flag) {
      $flags_ids[$flag->id()] = $flag->label();
    }

    return $flags_ids;
  }

}
