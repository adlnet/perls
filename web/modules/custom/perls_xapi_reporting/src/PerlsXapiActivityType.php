<?php

namespace Drupal\perls_xapi_reporting;

/**
 * List of predefined activity type.
 */
class PerlsXapiActivityType {

  // Content types.
  const ARTICLE = 'http://activitystrea.ms/schema/1.0/article';
  const TIP = 'http://id.tincanapi.com/activitytype/resource';
  const FLASHCARD = 'https://w3id.org/xapi/flashcards/activity-types/flashcard';
  const PODCAST_EPISODE = 'https://w3id.org/xapi/audio/activity-type/audio';
  const ASSESSMENT = 'http://adlnet.gov/expapi/activities/assessment';
  const QUESTION = 'http://adlnet.gov/expapi/activities/question';
  const COURSE = 'http://adlnet.gov/expapi/activities/course';
  const DOCUMENT = 'http://id.tincanapi.com/activitytype/document';
  const EVENT = 'http://activitystrea.ms/schema/1.0/event';

  // Content organization.
  const TOPIC = 'http://id.tincanapi.com/activitytype/category';
  const TAG = 'http://id.tincanapi.com/activitytype/tag';
  const GROUP = 'http://activitystrea.ms/schema/1.0/group';
  const REVIEW = 'http://activitystrea.ms/schema/1.0/review';

  // Self-regulated learning.
  const RECOMMENDATION = 'http://xapi.gowithfloat.net/activitytype/recommendation';
  const GOAL = 'http://id.tincanapi.com/activitytype/goal';
  const PROFILE = 'http://id.tincanapi.com/activitytype/user-profile';
  const COMMENT = 'http://activitystrea.ms/schema/1.0/comment';

  // Achievements.
  const BADGE = 'http://activitystrea.ms/schema/1.0/badge';
  const CERTIFICATE = 'https://www.opigno.org/en/tincan_registry/activity_type/certificate';

  // Task entity.
  const TASK = 'http://id.tincanapi.com/activitytype/goal';

}
