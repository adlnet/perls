<?php

namespace Drupal\perls_xapi_reporting;

use Drupal\user\UserInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\xapi\XapiActivityProvider;

/**
 * PERLS-specific activity provider.
 */
class PerlsXapiActivityProvider extends XapiActivityProvider {
  const DESCRIPTION_FIELD = 'field_description';
  const CARD_FRONT_FIELD = 'field_card_front';
  const BODY_FIELD = 'field_paragraph_body';

  /**
   * {@inheritdoc}
   */
  protected function getActivityType(EntityInterface $entity): ?string {
    $key = $entity->getEntityTypeId() . ':' . $entity->bundle();

    switch ($key) {
      case 'node:learn_article':
      case 'node:learn_link':
      case 'node:learn_package':
      case 'node:learn_file':
        return PerlsXapiActivityType::ARTICLE;

      case 'node:course':
        return PerlsXapiActivityType::COURSE;

      case 'node:tip_card':
        return PerlsXapiActivityType::TIP;

      case 'node:flash_card':
        return PerlsXapiActivityType::FLASHCARD;

      case 'node:quiz':
        return PerlsXapiActivityType::QUESTION;

      case 'node:test':
        return PerlsXapiActivityType::ASSESSMENT;

      case 'node:podcast':
        return NULL;

      case 'node:podcast_episode':
        return PerlsXapiActivityType::PODCAST_EPISODE;

      case 'node:event':
        return PerlsXapiActivityType::EVENT;

      case 'taxonomy_term:category':
        return PerlsXapiActivityType::TOPIC;

      case 'taxonomy_term:tags':
        return PerlsXapiActivityType::TAG;

      case 'user:user':
        return PerlsXapiActivityType::PROFILE;

      case 'file:file':
        return PerlsXapiActivityType::DOCUMENT;

      case 'comment:public_discussion':
        return PerlsXapiActivityType::COMMENT;

      case 'group:audience':
        return PerlsXapiActivityType::GROUP;

      case 'task:user_task':
        return PerlsXapiActivityType::TASK;

      default:
        return parent::getActivityType($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getActivityName(EntityInterface $entity): array {
    if ($entity instanceof UserInterface) {
      // Check if we're allowed to report real display names.
      if (\Drupal::config('xapi.settings')->get('real_name')) {
        $name = 'user profile for ' . $entity->getDisplayName();
      }
      else {
        $name = 'user profile';
      }
      // @todo Don't assume English.
      return ['en' => $name];
    }

    return parent::getActivityName($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getActivityDescription(EntityInterface $entity): array {
    $map = [];

    if ($entity instanceof TranslatableInterface) {
      foreach ($entity->getTranslationLanguages() as $lang_code => $language) {
        $translated_entity = $entity->getTranslation($lang_code);
        $description = $this->getDescription($translated_entity);
        if (!empty($description)) {
          $map[$lang_code] = $description;
        }
      }
    }

    return $map;
  }

  /**
   * Generates a description for the translated version of the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $translatedEntity
   *   An entity with an active translation (which may just be English).
   *
   * @return string|null
   *   The description for the translation.
   */
  private function getDescription(EntityInterface $translatedEntity): ?string {
    if ($translatedEntity->hasField(self::DESCRIPTION_FIELD)) {
      return trim($translatedEntity->get(self::DESCRIPTION_FIELD)->value);
    }
    elseif ($translatedEntity->hasField(self::CARD_FRONT_FIELD)) {
      // The card may have multiple paragraphs, so we concatenate all of them.
      $value = array_reduce($translatedEntity->get(self::CARD_FRONT_FIELD)->referencedEntities(), function ($result, $paragraph) {
        if ($paragraph->hasField(self::BODY_FIELD)) {
          return $result . "\n" . $paragraph->get(self::BODY_FIELD)->value;
        }

        return $result;
      });

      return trim(PlainTextOutput::renderFromHtml($value));
    }

    return NULL;
  }

}
