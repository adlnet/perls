<?php

declare(strict_types = 1);

namespace Drupal\business_rules_additions\Plugin\BusinessRulesAction;

use Drupal\business_rules\ItemInterface;
use Drupal\business_rules\Plugin\BusinessRulesActionPlugin;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * An abstract class to implement the settings form to front page actions.
 *
 * @package Drupal\business_rules_additions\Plugin\BusinessRulesAction
 */
abstract class FrontPageAction extends BusinessRulesActionPlugin {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(
    array &$form,
    FormStateInterface $form_state,
    ItemInterface $item
  ): array {
    $settings['promoted'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Promoted'),
      '#description' => $this->t("Select the 'promote' field above. All the entities where the promote field equals to this value will be promoted. By default, if an entity is not promoted, and this action is completed they will be promoted."),
      '#default_value' => $item->getSettings('promoted'),
      '#empty_option' => $this->t('- Select -'),
      '#empty_value' => '',
      '#options' => [
        0 => $this->t('Not Promoted'),
        1 => $this->t('Promoted'),
      ],
    ];

    return $settings;
  }

}
