<?php

namespace Drupal\perls_content_management\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a flag to an user.
 *
 * @Action(
 *   id = "flag_simple_recommendation_weight",
 *   label = @Translation("Recommend (Same details for all nodes)"),
 *   type = "node"
 * )
 */
class SimpleRecommendedContentFlag extends ViewsBulkOperationsActionBase implements PluginFormInterface, ContainerFactoryPluginInterface {

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
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerFactory;

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
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FlagServiceInterface $flag_service, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->flagService = $flag_service;
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->messenger = $messenger;
    $this->loggerFactory = $logger_factory;
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
      $container->get('messenger'),
      $container->get('logger.factory'),
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
    if ($user_id = $this->context['arguments'][0]) {
      $flag = $this->flagService->getFlagById($this->flagName);
      $account = $this->userStorage->load($user_id);
      $flagging = $this->flagService->getFlagging($flag, $node, $account);

      if (isset($flagging)) {
        $this->messenger->addMessage($this->t("This user has this flag on content the %content.", ['%content' => $node->label()]), MessengerInterface::TYPE_WARNING);
      }
      elseif (isset($account)) {
        try {
          $this->saveNewFlag($flag, $node, $account);
        }
        catch (EntityStorageException $e) {
          $this->messenger->addError($this->t('Something went wrong the system could not do the flag operation.'));
          $this->loggerFactory->get('perls_content_management')->error($e->getMessage());
        }

      }
    }
    else {
      $this->messenger->addMessage($this->t('This flag only works on /user/%user urls.'), MessengerInterface::TYPE_WARNING);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['flag_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Recommended content.'),
      '#open' => TRUE,
    ];

    $form['flag_details']['flag_weight'] = [
      '#type' => 'select',
      '#title' => $this->t('Weight'),
      '#options' => range(0, 20),
      '#description' => $this->t('You can assign weight to the recommended content. Minimum weight is 0.'),
      '#required' => TRUE,
    ];

    $form['flag_details']['flag_weight_details'] = [
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
    $form_values = $form_state->getValues();
    $this->configuration['flag_weight'] = [];
    $this->configuration['flag_weight']['weight'] = $form_values['flag_weight'];
    $this->configuration['flag_weight']['details'] = $form_values['flag_weight_details'];
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function saveNewFlag(FlagInterface $flag, EntityInterface $entity, AccountInterface $account) {
    $flagging = $this->flagService->flag($flag, $entity, $account);
    $weight_details = $this->configuration['flag_weight'];
    $flagging->set('field_recommendation_score', $weight_details['weight']);
    $flagging->set('field_recommendation_reason', $weight_details['details']);
    $flagging->save();
  }

}
