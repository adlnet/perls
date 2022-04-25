<?php

namespace Drupal\recommender\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the recommendation history.
 *
 * The recommendation history entity keeps a record of every recommenation
 * presented to the user.
 * This can be used by recommendation engine to score recommendations.
 * E.g give boost to first time recommendations etc.
 *
 * @ingroup recommender
 *
 * @ContentEntityType(
 *   id = "recommendation_history",
 *   label = @Translation("Recommendation History"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\recommender\RecommendationCandidateAccessControlHandler",
 *   },
 *   base_table = "sl_recommendation_history",
 *   translatable = FALSE,
 *   admin_permission = "administer recommendation candidate entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "nid",
 *   },
 * )
 */
class RecommendationHistory extends ContentEntityBase {
  use EntityChangedTrait;

  /**
   * Get created time.
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * Set created time.
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * Set the combined score.
   */
  public function setCombinedScore($score) {
    $this->set('combined_score', $score);
  }

  /**
   * Get the combined score.
   */
  public function getCombinedScore() {
    return $this->get('combined_score')->value;
  }

  /**
   * Set the recommendation reason.
   */
  public function setReason($reason) {
    $this->set('recommendation_reason', $reason);
  }

  /**
   * Get the recommendation reason.
   */
  public function getReason() {
    return $this->get('recommendation_reason')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['nid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Recommendation Candidate'))
      ->setDescription(t('The node ID of the Recommendation Candidate.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'node',
        'weight' => 1,
      ])
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ]);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user ID of the Recommendation Candidate.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\node\Entity\Node::getDefaultEntityOwner')
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'author',
        'weight' => 1,
      ])
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['recommendation_reason'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Recommendation Reason'))
      ->setDescription(t('The reason for recommending this content.'))
      ->setSettings([
        'max_length' => 5000,
        'text_processing' => 0,
      ])
      ->setCardinality(1)
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['combined_score'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Score'))
      ->setDescription(t('The recommendation score for this node.'))
      ->setDefaultValue(0)
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
