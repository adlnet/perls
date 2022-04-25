<?php

namespace Drupal\notifications\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Json;

/**
 * Form controller for notifications edit forms.
 *
 * @ingroup notifications
 */
class PushNotificationForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Avoid changing recipients or topics for now.
    $form['recipients']['#access'] = FALSE;
    $form['topics']['#access'] = FALSE;

    // Hide json cotent and just show body text for editing.
    $json = $form['content']['widget'][0]['value']['#default_value'];
    if ($json) {
      $message_content = Json::decode($json);
      if ($message_content && isset($message_content['notification']) && isset($message_content['notification']['body'])) {
        $body_text = $message_content['notification']['body'];
        $form['content']['#access'] = FALSE;
        $form['message_body'] = [
          '#weight' => $form['content']['#weight'],
          '#type' => 'textarea',
          '#title' => $this->t('Message'),
          '#required' => TRUE,
          '#placeholder' => $this->t('Type a message'),
          '#default_value' => $body_text,
          '#rows' => 4,
          '#resizable' => 'none',
          '#attributes' => [
            'maxlength' => 1000,
          ],
        ];
      }
    }

    $form['send_time']['widget'][0]['value'] += [
      '#size' => 20,
      '#date_date_element' => 'date',
      '#date_time_element' => 'time',
      '#date_increment' => 60 * 15,
      '#date_time_callbacks' => ['_notifications_datetime_time'],
    ];
    unset($form['send_time']['widget'][0]['value']['#description']);

    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('JSON'),
      '#weight' => 100,
    ];

    $form['info']['json'] = [
      '#markup' => '<pre>' . json_encode(json_decode($json), JSON_PRETTY_PRINT) . '</pre>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Transfer data from title and body text_fields to message json.
    $values = $form_state->getValues();
    $json = $values['content'][0]['value'];
    $message_content = Json::decode($json);
    $message_altered = FALSE;
    // Update body.
    if ($values['message_body'] && $values['message_body'] != $message_content['notification']['body']) {
      $message_content['notification']['body'] = $values['message_body'];
      $message_altered = TRUE;
    }
    // Update title.
    if ($values['title'] && $values['title'][0]['value'] != $message_content['notification']['title']) {
      $message_content['notification']['title'] = $values['title'][0]['value'];
      $message_altered = TRUE;
    }
    if ($message_altered) {
      $json = Json::encode($message_content);
      $form_state->setValue('content', [0 => ['value' => $json]]);
    }
    parent::submitForm($form, $form_state);
  }

}
