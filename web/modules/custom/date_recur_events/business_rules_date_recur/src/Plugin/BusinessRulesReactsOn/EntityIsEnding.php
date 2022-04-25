<?php

declare(strict_types = 1);

namespace Drupal\business_rules_date_recur\Plugin\BusinessRulesReactsOn;

use Drupal\business_rules\Plugin\BusinessRulesReactsOnPlugin;

/**
 * Business Rules Plugin to react and create actions when an Entity is ending.
 *
 * @package Drupal\business_rules_date_recur\Plugin\BusinessRulesReactsOn
 *
 * @\Drupal\business_rules\Annotation\BusinessRulesReactsOn(
 *   id = "entity_is_ending",
 *   label = @Translation("Entity is ending"),
 *   description = @Translation("Reacts when an entity is ending."),
 *   group = @Translation("Entity"),
 *   eventName = "business_rules.date_recur.ending",
 *   hasTargetEntity = TRUE,
 *   hasTargetBundle = TRUE,
 *   priority = 1000,
 * )
 */
class EntityIsEnding extends BusinessRulesReactsOnPlugin {

}
