<?php

namespace Drupal\perls_content_management\Plugin\Action;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsPreconfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a flag to an user.
 *
 * @Action(
 *   id = "unflag_other_user",
 *   label = @Translation("Unflag an other user's flag."),
 *   type = ""
 * )
 */
class UnFlagOtherUser extends ViewsBulkOperationsActionBase implements ViewsBulkOperationsPreconfigurationInterface, ContainerFactoryPluginInterface {

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
   * FlagOtherUser constructor.
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
  public function execute($entity = NULL) {
    // This only available for page with user/%user url.
    $flag_id = $this->configuration['flag'];
    $flag = $this->flagService->getFlagById($flag_id);
    $user_id = $this->context['arguments'][0];
    $account = $this->userStorage->load($user_id);
    $flagging = $this->flagService->getFlagging($flag, $entity, $account);
    if (isset($account) && !empty($flagging)) {
      $this->flagService->unflag($flag, $entity, $account);
      return $flag->getMessage('unflag') ?: $this->t('@label removed', ['@label' => $flag->label()]);
    }
    else {
      $this->messenger->addMessage($this->t("This user doesn't have this flag."), MessengerInterface::TYPE_WARNING);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPreConfigurationForm(array $form, array $values, FormStateInterface $form_state) {
    $flags = $this->flagService->getAllFlags();
    $flags_ids = [];
    foreach ($flags as $key => $flag) {
      $flags_ids[$key] = $flag->id();
    }

    $form['flag'] = [
      '#title' => $this->t('Flags'),
      '#description' => $this->t('Select which flag will be removed.'),
      '#type' => 'radios',
      '#options' => $flags_ids,
      '#default_value' => isset($values['flag']) ? $values['flag'] : '',
    ];

    return $form;
  }

}
