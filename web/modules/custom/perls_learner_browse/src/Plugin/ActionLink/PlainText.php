<?php

namespace Drupal\perls_learner_browse\Plugin\ActionLink;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\Plugin\ActionLink\Reload;

/**
 * Provides the AJAX link type.
 *
 * This class is an extension of the Reload link type, but modified to
 * provide AJAX links.
 *
 * @ActionLinkType(
 *   id = "plain_text",
 *   label = @Translation("Plain text"),
 *   description = "Show the flag status as plain text"
 * )
 */
class PlainText extends Reload {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $options = parent::defaultConfiguration();

    $options += [
      'markup_text_flag' => '',
      'markup_text_unflag' => '',
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getAsFlagLink(FlagInterface $flag, EntityInterface $entity) {
    $action = $this->getAction($flag, $entity);
    $access = $flag->actionAccess($action, $this->currentUser, $entity);
    $render = [];

    if ($action === 'flag') {
      $text_display = $this->configuration['markup_text_flag'];
    }
    else {
      $text_display = $this->configuration['markup_text_unflag'];
    }

    if ($action === 'unflag') {
      $url = $this->getUrl($action, $flag, $entity);
      $url->setRouteParameter('destination', $this->getDestination());
      $render = [
        '#type' => 'container',
        'flag_text' => [
          '#markup' => $text_display,
        ],
        '#attributes' => [
          'class' => [
            'flag',
            Html::cleanCssIdentifier(mb_strtolower($text_display)),
          ],
        ],
      ];
    }

    $render = [
      '#markup' => \Drupal::getContainer()->get('renderer')->renderPlain($render),
    ];

    CacheableMetadata::createFromRenderArray($render)
      ->addCacheableDependency($access)
      ->applyTo($render);

    return $render;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['display']['settings']['markup_text_flag'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Flag status text'),
      '#default_value' => $this->configuration['markup_text_flag'],
      '#description' => $this->t('Text displayed if an entity is not flagged."'),
    ];

    $form['display']['settings']['markup_text_unflag'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unflag status text'),
      '#default_value' => $this->configuration['markup_text_unflag'],
      '#description' => $this->t('Text displayed if an entity is flagged"'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    foreach (array_keys($this->defaultConfiguration()) as $key) {
      $this->configuration[$key] = $form_state->getValue($key);
    }
  }

}
