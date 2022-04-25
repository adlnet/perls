<?php

namespace Drupal\notifications\Entity\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for cancelling queued  push notification entities.
 *
 * @ingroup notifications
 */
class PushNotificationSendNowForm extends ContentEntityConfirmFormBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, MessengerInterface $messenger) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t("Sending %title to @recipients", [
      '%title' => $this->entity->label(),
      '@recipients' => $this->getRecipients(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will send a notification to @recipients.', [
      '@recipients' => $this->getRecipients(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->getEntity()->toUrl('canonical');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Send Now');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $builder = $this->entityTypeManager->getViewBuilder($this->entity->getEntityTypeId());
    $form['notification'] = $builder->view($this->entity, 'teaser');
    $form['notification']['#weight'] = 1;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messageService = \Drupal::service('notifications.firebase.message');
    \Drupal::logger('notifications')
      ->notice('Sending notification \'@title\' to all recipients', ['@title' => $this->entity->label()]);
    $messageService->sendMessage($this->entity);
    $this->messenger->addMessage(
      $this->t("%label has been sent.",
        [
          '%label' => $this->entity->label(),
        ]
        )
    );

    $form_state->setRedirectUrl(new Url('page_manager.page_view_push_notification_list_push_notification_list-block_display-0'));
  }

  /**
   * Retrieves a summary of the recipients of the message.
   */
  protected function getRecipients() {
    $recipients = $this->entity->getUsers();
    $recipient = $recipients->first() !== NULL ? $recipients->first()->entity : NULL;
    if (!$recipient) {
      return $this->t('nobody');
    }

    return $recipient->getEntityType()->getCountLabel(count($recipients));
  }

}
