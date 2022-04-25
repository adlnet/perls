<?php

namespace Drupal\recommender\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the recommendation candidate status entity.
 *
 * A recommendation candidate is a potential recommendation created by
 * a recommendation plugin which will be scored and may be presented
 * as a recommenation to the user at the end of the recommendation process.
 *
 * @ingroup recommender
 *
 * @ContentEntityType(
 *   id = "recommendation_candidate",
 *   label = @Translation("Recommendation Candidate"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\recommender\RecommendationCandidateAccessControlHandler",
 *   },
 *   base_table = "sl_recommendation_candidate",
 *   translatable = FALSE,
 *   admin_permission = "administer recommendation candidate entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "nid",
 *   },
 * )
 */
class RecommendationCandidate extends ContentEntityBase {
  use EntityChangedTrait;

  const STATUS_QUEUED = 'Queued';
  const STATUS_PROCESSING = 'Processing';
  const STATUS_READY = 'Ready';

  /**
   * Get the current value of status.
   */
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * Set the entities status.
   */
  public function setStatus($status) {
    $this->set('status', $status);
  }

  /**
   * Get the last time recommendations were updated.
   */
  public function getUpdated() {
    return $this->get('updated')->value;
  }

  /**
   * Set the time that this users recommendations were last updated.
   */
  public function setUpdated($time) {
    $this->set('updated', $time);
  }

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
   *
   * The combine score is the final score given to this candidate.
   * It is this score that is used to determine the recommendation position.
   *
   * @param float $score
   *   The score value for the candidate.
   */
  public function setCombinedScore($score) {
    $this->set('combined_score', $score);
  }

  /**
   * Get the combined score.
   *
   * The combine score is the final score given to this candidate.
   * It is this score that is used to determine the recommendation position.
   */
  public function getCombinedScore() {
    return $this->get('combined_score')->value;
  }

  /**
   * Set the recommendation reason.
   *
   * The recommendation reason is the information given to the user to
   * explain why this item has been recommended.
   *
   * @param string $reason
   *   The reason to be shown to the user.
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

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Recommendation Engine Status'))
      ->setDescription(t('This users current status on the recommendation engine.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
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

    $fields['scores'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Scores'))
      ->setDescription(t('The scores given to the Recommendation Candidate by the plugins.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'recommendation_plugin_score')
      ->setSetting('handler', 'default')
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 1,
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
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

    return $fields;
  }

}
