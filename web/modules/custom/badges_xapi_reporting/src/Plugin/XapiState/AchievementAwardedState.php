<?php

namespace Drupal\badges_xapi_reporting\Plugin\XapiState;

use Drupal\perls_learner_state\Plugin\XapiStateBase;
use Drupal\perls_xapi_reporting\PerlsXapiActivityType;
use Drupal\user\UserInterface;

/**
 * Define achievement awarded state.
 *
 * @XapiState(
 *  id = "xapi_achievement_awarded_state",
 *  label = @Translation("Xapi badge awarded state"),
 *  add_verb = @XapiVerb("earned"),
 *  remove_verb = NULL,
 *  notifyOnXapi = FALSE,
 *  flag = ""
 * )
 */
class AchievementAwardedState extends XapiStateBase {

  /**
   * {@inheritdoc}
   */
  public function prepareStatement($timestamp = NULL, UserInterface $user = NULL) {
    global $base_url;

    parent::prepareStatement($timestamp, $user);

    // Activity.
    if (!empty($this->getStatementContent())) {
      // The achievement entity for this statement.
      /** @var \Drupal\badges\ExtendedAchievementInterface $achievement */
      $achievement = $this->getStatementContent();
      $activity_id = $base_url . '/achievement/' . $achievement->uuid() . '/' . $achievement->getType();
      $type = ($achievement->getType() === 'badge') ? PerlsXapiActivityType::BADGE : PerlsXapiActivityType::CERTIFICATE;

      $this->statement->getObject()
        ->setType($type)
        ->setId($activity_id);
    }
  }

}
