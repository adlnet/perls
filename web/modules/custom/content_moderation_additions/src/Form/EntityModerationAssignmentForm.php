<?php

namespace Drupal\content_moderation_additions\Form;

use Drupal\Component\Datetime\Time;
use Drupal\content_moderation\Form\EntityModerationForm;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidation;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\content_moderation_additions\ModerationNodeReviewerInterface;
use Drupal\user\Entity\User;
use Drupal\workflows\Transition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A more advanced entity moderation form allowing for reviewers to be assigned.
 */
class EntityModerationAssignmentForm extends EntityModerationForm {

  /**
   * A reference to the service that provides/updates reviewer infomation.
   *
   * @var Drupal\content_moderation_additions\ModerationNodeReviewerInterface
   */
  protected $reviewerService;

  /**
   * A reference to the config for this module.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * EntityModerationForm constructor.
   *
   * @param \Drupal\content_moderation_additions\ModerationNodeReviewerInterface $reviewerService
   *   The service that updates/saves reviewer information.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   * @param \Drupal\content_moderation\StateTransitionValidation $validation
   *   The moderation state transition validation service.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ModerationNodeReviewerInterface $reviewerService, ModerationInformationInterface $moderation_info, StateTransitionValidation $validation, Time $time, ConfigFactoryInterface $config_factory) {
    parent::__construct($moderation_info, $validation, $time);
    $this->reviewerService = $reviewerService;
    $this->config = $config_factory->get('content_moderation_additions.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('content_moderation_additions.node_reviewer'),
      $container->get('content_moderation.moderation_information'),
      $container->get('content_moderation.state_transition_validation'),
      $container->get('datetime.time'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_moderation_entity_moderation_assignment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $entity = NULL) {
    $parent_form = parent::buildForm($form, $form_state, $entity);

    $current_state = $entity->moderation_state->value;
    $transitions = $this->validation->getValidTransitions($entity, $this->currentUser());

    if (empty($transitions)) {
      $form['#access'] = FALSE;
      return $form;
    }

    // Exclude self-transitions.
    $transitions = array_filter($transitions, function (Transition $transition) use ($current_state) {
      return $transition->to()->id() != $current_state;
    });

    $form['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->getEntityStateDescription($entity),
      '#weight' => -1,
    ];

    // Prepare options for the reviewer select field.
    if ($this->useReviewer()) {
      $options = [$this->t('No one')];
      $accounts = User::loadMultiple($this->reviewerService->getValidReviewers($entity));

      $options += array_map(function ($account) {
        return $account->getDisplayName();
      }, $accounts);

      $form['reviewer_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Reviewer'),
        '#options' => $options,
        '#default_value' => $this->reviewerService->getCurrentReviewer($entity) ?: 0,
        '#weight' => 0,
      ];

      if (empty($accounts)) {
        hide($form['reviewer_id']);
      }
    }

    $form['actions'] = [
      '#type' => 'container',
      '#weight' => 100,
    ];

    foreach (array_values($transitions) as $i => $transition) {
      $class = $i == 0 ? 'primary' : '';
      $form['actions'][$transition->id()] = [
        '#type' => 'submit',
        '#name' => $transition->to()->id(),
        '#value' => $transition->label(),
        '#attributes' => [
          'class' => [$class],
        ],
      ];
    }

    $form['revision_id'] = [
      '#type' => 'textfield',
      '#value' => $entity->getLoadedRevisionId(),
      '#access' => FALSE,
    ];

    // Reuse and customize revision_log field from the original form.
    $form['revision_log'] = $parent_form['revision_log'];
    $form['revision_log']['#type'] = 'textarea';
    $form['revision_log']['#placeholder'] = $this->t('Leave a note...');
    $form['revision_log']['#weight'] = 1;
    $form['revision_log']['#maxlength'] = 1000;

    if ($this->useComments()) {
      $form['actions']['update'] = [
        '#type' => 'submit',
        '#name' => 'update',
        '#value' => $this->t('Add comment'),
      ];

      $form['actions']['view-discussion'] = [
        '#type' => 'link',
        '#title' => $this->t('View discussion'),
        '#url' => Url::fromRoute(\Drupal::routeMatch()->getRouteName(),
        [
          'node' => $entity->id(),
          'node_revision' => $entity->getLoadedRevisionId(),
        ],
        ['fragment' => 'moderation-comments']),
      ];
    }

    $form['#attributes']['class'][] = 'entity-moderation';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $new_state = $form_state->getTriggeringElement()['#name'];
    $form_state->setValue('new_state', $new_state);

    $entity = $form_state->get('entity');
    // Only check reviewer on states with reviewer.
    if ($this->useReviewer($entity)) {
      $reviewer_id = $form_state->getValue('reviewer_id');
      if ($reviewer_id) {
        if (!$this->reviewerService->isValidReviewer($entity, $reviewer_id)) {
          $form_state->setError($form['reviewer_id'], $this->t('Choose a valid reviewer'));
        }
      }
      elseif (empty($reviewer_id) && $new_state === 'review') {
        if (!empty($this->reviewerService->getValidReviewers($entity))) {
          $form_state->setError($form['reviewer_id'], $this->t('You need to choose someone to review this content'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $form_state->get('entity');

    if ($form_state->getTriggeringElement()['#name'] !== 'update') {
      $form_state->setValue('comment_revision', $form_state->getValue('revision_id'));
      parent::submitForm($form, $form_state);

      // The parent class creates a new entity revision when saving this form;
      // to correctly set the reviewer, the reference needs to be updated
      // so it points to the latest revision ID.
      $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
      $newRevision = $storage->getLatestRevisionId($entity->id());
      $entity = $storage->loadRevision($newRevision);
    }
    elseif ($this->useComments()) {
      $message = $form_state->getValue('revision_log');
      $vid = $form_state->getValue('revision_id');

      \Drupal::service('content_moderation_additions.comment_storage')->postComment($entity, $this->currentUser(), $message, $vid);
    }
    if ($this->useReviewer()) {
      $this->reviewerService->setCurrentReviewer($entity, $form_state->getValue('reviewer_id'));
    }
  }

  /**
   * Retrieves a description about the current state of the entity.
   */
  protected function getEntityStateDescription($entity) {
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);

    $vars = [
      '%title' => $entity->getTitle(),
      '%state' => $workflow->getTypePlugin()->getState($entity->moderation_state->value)->label(),
    ];

    if (!$entity->isPublished()) {
      if (!$this->moderationInfo->isDefaultRevisionPublished($entity)) {
        return $this->t('%title is in a %state state; users will not see it until it is published.', $vars);
      }
      else {
        return $this->t('This new version of %title is in a %state state; users will not see this until it is published.', $vars);
      }
    }
  }

  /**
   * Check if this entity needs reviewer moderation.
   */
  private function useReviewer() {
    return $this->config->get('enable_reviewer') ?: 0;
  }

  /**
   * Check if this entity needs reviewer moderation.
   */
  private function useComments() {
    return $this->config->get('enable_moderation_comments') ?: 0;
  }

}
