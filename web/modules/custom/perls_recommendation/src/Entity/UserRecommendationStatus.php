<?php

namespace Drupal\perls_recommendation\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the User recommendation status entity.
 *
 * @ingroup perls_recommendation
 *
 * @ContentEntityType(
 *   id = "user_recommendation_status",
 *   label = @Translation("User recommendation status"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\perls_recommendation\UserRecommendationStatusListBuilder",
 *     "views_data" = "Drupal\perls_recommendation\Entity\UserRecommendationStatusViewsData",
 *
 *     "access" = "Drupal\perls_recommendation\UserRecommendationStatusAccessControlHandler",
 *   },
 *   base_table = "user_recommendation_status",
 *   translatable = FALSE,
 *   admin_permission = "administer user recommendation status entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "user_id",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class UserRecommendationStatus extends ContentEntityBase {

  use EntityChangedTrait;

  const STATUS_QUEUED = 'Queued';
  const STATUS_PROCESSING = 'Processing';
  const STATUS_READY = 'Ready';
  const STATUS_RETRIEVED = 'Retrieved';

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
   * Get the number of recommendations retrieved.
   */
  public function getRetrieved() {
    return $this->get('recommendations_retrieved')->value;
  }

  /**
   * Set the number or recommendations retrieved.
   */
  public function setRetrieved($retrieved) {
    $this->set('recommendations_retrieved', $retrieved);
  }

  /**
   * Get the last time recommendations were updated.
   */
  public function getUpdated() {
    return $this->get('recommendations_updated')->value;
  }

  /**
   * Set the time that this users recommendations were last updated.
   */
  public function setUpdated($time) {
    $this->set('recommendations_updated', $time);
  }

  /**
   * Get the last time recommendations were updated.
   */
  public function getLastRecId() {
    return $this->get('recommendations_uuid')->value;
  }

  /**
   * Set the time that this users recommendations were last updated.
   */
  public function setLastRecId($uuid) {
    $this->set('recommendations_uuid', $uuid);
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
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user ID of this recommendation status.'))
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

    $fields['recommendations_updated'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Recommendations last updated'))
      ->setDescription(t('The time that the recoomendations were last updated.'));

    $fields['recommendations_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Recommendation UUID'))
      ->setDescription(t('A uuid unique to the current recommendations (uuid is updated if new recommendations are available).'));

    $fields['recommendations_retrieved'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Recommendations retrieved'))
      ->setDescription(t('The number of recommendations retrieved on last refresh.'));

    return $fields;
  }

}
