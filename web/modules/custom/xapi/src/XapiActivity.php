<?php

namespace Drupal\xapi;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityInterface;

/**
 * XAPI Activity builder.
 */
class XapiActivity implements \JsonSerializable {

  /**
   * The activity.
   *
   * @var array
   */
  protected $activity;

  /**
   * The activity provider.
   *
   * @var \Drupal\xapi\XapiActivityProviderInterface
   */
  protected $activityProvider;

  /**
   * Constructs a new XapiActivity builder.
   *
   * @param XapiActivityProviderInterface $activity_provider
   *   The current activity provider.
   */
  public function __construct(
    XapiActivityProviderInterface $activity_provider
  ) {
    $this->activityProvider = $activity_provider;
  }

  /**
   * Creates an XapiActivity builder.
   *
   * @return XapiActivity
   *   A new builder object.
   */
  public static function create() {
    return new self(
      \Drupal::service('xapi.activity_provider'),
    );
  }

  /**
   * Creates an XapiActivity builder and populate it from an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return XapiActivity
   *   A new builder object.
   */
  public static function createFromEntity(EntityInterface $entity): XapiActivity {
    $activity = self::create();
    $activity->fromEntity($entity);
    return $activity;
  }

  /**
   * Populates the activity based on the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return XapiActivity
   *   The current XapiActivity builder.
   */
  public function fromEntity(EntityInterface $entity): XapiActivity {
    return $this->setActivity($this->activityProvider->getActivity($entity));
  }

  /**
   * Populates the activity from an existing activity.
   *
   * @param array $activity
   *   An xAPI activity.
   *
   * @return XapiActivity
   *   The current XapiActivity builder.
   */
  public function setActivity(array $activity): XapiActivity {
    $this->activity = $activity;
    return $this;
  }

  /**
   * Get the id property.
   *
   * @return string
   *   The id.
   */
  public function getId() {
    return $this->activity['id'];
  }

  /**
   * Sets the activity ID using the `$base_url` and the provided path.
   *
   * @param string $id
   *   The relative path; will be prefixed with the site URL.
   *
   * @return XapiActivity
   *   The current XapiActivity builder.
   */
  public function setRelativeId(string $id): XapiActivity {
    global $base_url;
    return $this->setId($base_url . '/' . $id);
  }

  /**
   * Set the id property.
   *
   * @param string $id
   *   The id.
   *
   * @return XapiActivity
   *   The current XapiActivity builder.
   */
  public function setId(string $id): XapiActivity {
    $this->activity['id'] = $id;
    return $this;
  }

  /**
   * Sets the activity name.
   *
   * @param array|string $name
   *   Either a language map or just a single label for the activity name.
   * @param string $languageCode
   *   An optional language specifier (defaults to English).
   *
   * @return XapiActivity
   *   The current XapiActivity builder.
   */
  public function setName($name, string $languageCode = 'en'): XapiActivity {
    $this->activity['definition']['name'] = $this->getLanguageMap($name, $languageCode);
    return $this;
  }

  /**
   * Sets the activity description.
   *
   * @param array|string $description
   *   Either a language map or a single label for the activity description.
   * @param string $languageCode
   *   An optional language specifier (defaults to English).
   *
   * @return XapiActivity
   *   The current XapiActivity builder.
   */
  public function setDescription($description, string $languageCode = 'en'): XapiActivity {
    $this->activity['definition']['description'] = $this->getLanguageMap($description, $languageCode);
    return $this;
  }

  /**
   * Sets the activity type.
   *
   * @param string $type
   *   The activity type.
   *
   * @return XapiActivity
   *   The current XapiActivity builder.
   */
  public function setType(string $type): XapiActivity {
    $this->activity['definition']['type'] = $type;
    return $this;
  }

  /**
   * Sets moreInfo on the activity.
   *
   * @param \Drupal\Core\Url $moreInfoUri
   *   An IRI to a document with human-readable information.
   *
   * @return XapiActivity
   *   The current XapiActivity builder.
   */
  public function setMoreInfo(Url $moreInfoUri): XapiActivity {
    $this->activity['definition']['moreInfo'] = $moreInfoUri->toString();
    return $this;
  }

  /**
   * Adds extensions to the activity definition.
   *
   * @param array $extensions
   *   The extensions to append to the activity definition.
   *
   * @return XapiActivity
   *   The current XapiActivity builder.
   */
  public function addExtensions(array $extensions): XapiActivity {
    if (!isset($this->activity['definition']['extensions'])) {
      $this->activity['definition']['extensions'] = [];
    }

    $this->activity['definition']['extensions'] += $extensions;

    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function jsonSerialize() {
    return $this->activity;
  }

  /**
   * Generates a language map based on the provided value.
   *
   * Wherever a language map is accepted, the function arguments will accept
   * a pre-made language map, or a single string value which will be made
   * into a language map.
   *
   * @param array|string $value
   *   A pre-made language map or a string value to put into a language map.
   * @param string $languageCode
   *   An optional language specifier (defaults to English).
   *
   * @return array
   *   The language map.
   */
  private function getLanguageMap($value, string $languageCode = 'en'): array {
    if (is_array($value)) {
      return $value;
    }

    return [$languageCode => $value];
  }

}
