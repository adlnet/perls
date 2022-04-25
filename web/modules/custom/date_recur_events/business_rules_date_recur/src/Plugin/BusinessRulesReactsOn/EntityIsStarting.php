<?php

declare(strict_types = 1);

namespace Drupal\business_rules_date_recur\Plugin\BusinessRulesReactsOn;

use Drupal\business_rules\Plugin\BusinessRulesReactsOnPlugin;

/**
 * Business Rules Plugin to react and create actions when an Entity is starting.
 *
 * @package Drupal\business_rules_date_recur\Plugin\BusinessRulesReactsOn
 *
 * @\Drupal\business_rules\Annotation\BusinessRulesReactsOn(
 *   id = "entity_is_starting",
 *   label = @Translation("Entity is starting"),
 *   description = @Translation("Reacts when an entity is starting."),
 *   group = @Translation("Entity"),
 *   eventName = "business_rules.date_recur.starting",
 *   hasTargetEntity = TRUE,
 *   hasTargetBundle = TRUE,
 *   priority = 1000,
 * )
 */
class EntityIsStarting extends BusinessRulesReactsOnPlugin {

}
