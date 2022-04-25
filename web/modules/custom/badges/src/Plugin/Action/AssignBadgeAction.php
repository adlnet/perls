<?php

namespace Drupal\badges\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\badges\Service\BadgeService;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Assign user badges.
 *
 * @Action(
 *   id = "badges_assign_badge",
 *   label = @Translation("Award a badge to users"),
 *   type = "user",
 *   confirm = FALSE
 * )
 */
class AssignBadgeAction extends ViewsBulkOperationsActionBase implements PluginFormInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The badge Service.
   *
   * @var \Drupal\badges\Service\BadgeService
   */
  protected $badgeService;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('badges.badge_service')
    );
  }

  /**
   * Constructor for Assign Badge Action.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    BadgeService $badge_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->badgeService = $badge_service;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = $this->badgeService->listBadgeTypes();
    $form['badge_type'] = [
      '#title' => $this->t('Badge'),
      '#description' => $this->t('Choose the Badge to be assigned.'),
      '#type' => 'select',
      '#options' => $options,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($user = NULL) {
    if (!$user || $user->getEntityTypeId() !== 'user') {
      return $this->t('This action can only be performed on Users');
    }
    if (!isset($this->configuration['badge_type'])) {
      return $this->t('This action requires that you choose a badge to apply.');
    }
    $this->badgeService->awardBadge($user, $this->configuration['badge_type']);
    return $this->t('Assigned @badge to @user.',
    [
      '@badge' => $this->configuration['badge_type'],
      '@user' => $user->label(),
    ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return AccessResult::allowedIf($account->hasPermission('administer user badges'));
  }

}
