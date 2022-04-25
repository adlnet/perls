<?php

namespace Drupal\xapi;

use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;

/**
 * Xapi Statement builder.
 */
class XapiStatement implements \JsonSerializable {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The activity provider.
   *
   * @var \Drupal\xapi\XapiActivityProviderInterface
   */
  protected $activityProvider;

  /**
   * The actor of statement.
   *
   * @var \Drupal\xapi\XapiActor
   */
  protected $actor;

  /**
   * A verb definition.
   *
   * @var \Drupal\xapi\XapiVerb
   */
  protected $verb;

  /**
   * The statement activity.
   *
   * @var \Drupal\xapi\XapiActivity
   */
  protected $object;

  /**
   * The timestamp when the object created.
   *
   * @var string
   */
  protected $timestamp;

  /**
   * Contains a statement xApi contexts.
   *
   * @var array
   */
  protected $context = [];

  /**
   * The statement result.
   *
   * @var array
   */
  protected $result = [];

  /**
   * Contains information about the platform.
   *
   * Currently this property contians three sub property:
   *   - domain: Domain address where the was created.
   *   - name: The site/platform name.
   *   - platform: Desktop/Mobile
   *   - version: The actual version of this platform.
   *
   * @var array
   */
  protected $platform = [];

  /**
   * XapiStatement constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param XapiActorIFIManager $ifi_manager
   *   The current IFI manager.
   * @param \Drupal\xapi\XapiActivityProviderInterface $activity_provider
   *   The activity provider.
   */
  public function __construct(
    AccountInterface $current_user,
    ConfigFactoryInterface $config_factory,
    XapiActorIFIManager $ifi_manager,
    XapiActivityProviderInterface $activity_provider
  ) {
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->activityProvider = $activity_provider;
    $this->actor = new XapiActor($config_factory, $ifi_manager);
    $this->object = new XapiActivity($activity_provider);
    $this->setDefaultPlatform();
  }

  /**
   * Creates a new XapiStatement builder.
   *
   * @return XapiStatement
   *   The new XapiStatement builder.
   */
  public static function create(): XapiStatement {
    return new self(
      \Drupal::currentUser(),
      \Drupal::configFactory(),
      \Drupal::service('plugin.manager.xapi_actor_ifi'),
      \Drupal::service('xapi.activity_provider'),
    );
  }

  /**
   * Sets the actor of the statement to the current Drupal user.
   *
   * If the user is the anonymous user, assumes the actor is the system.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function setActorToCurrentUser(): XapiStatement {
    return $this->setActor($this->currentUser);
  }

  /**
   * Sets the actor to the system.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function setActorToSystem(): XapiStatement {
    global $base_url;

    $system_name = $this->configFactory->get('system.site')->get('name');
    $this->actor
      ->setName($system_name)
      ->setAccount($system_name);

    return $this;
  }

  /**
   * Sets the actor of the statement.
   *
   * @param \Drupal\Core\Session\AccountInterface|XapiActor $actor
   *   The actor.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function setActor($actor): XapiStatement {
    if ($actor->id() == 0) {
      return $this->setActorToSystem();
    }

    if ($actor instanceof UserInterface) {
      $this->actor->fromUser($actor);
    }
    elseif ($actor instanceof AccountInterface) {
      $user = User::load($actor->id());
      if ($user) {
        $this->actor->fromUser($user);
      }
    }

    if ($actor instanceof XapiActor) {
      $this->actor = $actor;
    }

    return $this;
  }

  /**
   * Set a verb.
   *
   * @param \Drupal\xapi\XapiVerb|string $verb
   *   The verb for the statement.
   * @param string $display
   *   The display value for the verb.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   *
   * @throws InvalidArgumentException
   *   If $verb doesn't appear to be a valid verb.
   */
  public function setVerb($verb, string $display = NULL): XapiStatement {
    if ($verb instanceof XapiVerb) {
      $this->verb = $verb;
    }
    elseif (is_string($verb)) {
      $this->verb = new XapiVerb($verb, $display);
    }
    else {
      throw new \InvalidArgumentException('verb must be either an XapiVerb or a URI');
    }

    return $this;
  }

  /**
   * Get the actor property.
   *
   * @return \Drupal\xapi\XapiActor
   *   The actor property.
   */
  public function getActor() {
    return $this->actor;
  }

  /**
   * Set the timestamp property.
   *
   * @param string $timestamp
   *   A timestamp when statements created.
   */
  public function setTimestamp($timestamp) {
    $this->timestamp = date('c', $timestamp);
  }

  /**
   * Sets the object of the statement.
   *
   * Could be an activity, an agent, or another statement.
   *
   * @param mixed $object
   *   The object of the statement.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function setObject($object): XapiStatement {
    if ($object instanceof UserInterface) {
      $this->object = XapiActor::createWithUser($object);
    }
    else {
      $this->setActivity($object);
    }

    return $this;
  }

  /**
   * Sets the statement object to an activity.
   *
   * @param array|XapiActivity|EntityInterface $object
   *   The object to generate an activity from.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   *
   * @throws InvalidArgumentException
   *   If the provided object cannot be an xAPI activity.
   */
  public function setActivity($object): XapiStatement {
    $this->object = $this->getActivity($object);
    return $this;
  }

  /**
   * Sets the statement object to an activity representing the system.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function setActivityToSystem(): XapiStatement {
    $name = $this->configFactory->get('system.site')->get('name');

    $this->object->setRelativeId('')
      ->setName($name)
      ->setType('http://activitystrea.ms/schema/1.0/application');

    return $this;
  }

  /**
   * Get the activity object.
   *
   * @return \Drupal\xapi\XapiActivity
   *   The activity object.
   */
  public function getObject() {
    return $this->object;
  }

  /**
   * Returns the context property.
   *
   * The array should looks like this.
   *
   * @return array
   *   The context property.
   */
  public function getContext() {
    if ($this->object instanceof XapiActivity) {
      $this->context += ['platform' => $this->getPlatformText()];
    }

    return $this->context;
  }

  /**
   * Set the object extension property.
   *
   * @param array $extensions
   *   Extensions to append to the object.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function addObjectExtensions(array $extensions): XapiStatement {
    $this->object->addExtensions($extensions);
    return $this;
  }

  /**
   * Set the context extension property.
   *
   * @param array $extensions
   *   Extensions to append to the context.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function addContextExtensions(array $extensions): XapiStatement {
    if (!isset($this->context['extensions'])) {
      $this->context['extensions'] = [];
    }

    $this->context['extensions'] += $extensions;

    return $this;
  }

  /**
   * Sets a registration that the statement is associated with.
   *
   * @param string $registration
   *   The UUID registration.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function setRegistration(string $registration): XapiStatement {
    $this->context['registration'] = $registration;
    return $this;
  }

  /**
   * Adds an activity to the parent context activities.
   *
   * @param array|XapiActivity|EntityInterface $activity
   *   The object to add as a parent context activity.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function addParentContext($activity): XapiStatement {
    return $this->addContextActivity($this->getActivity($activity), 'parent');
  }

  /**
   * Adds an activity to the grouping context activities.
   *
   * @param array|XapiActivity|EntityInterface $activity
   *   The object to add as a grouping context activity.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function addGroupingContext($activity): XapiStatement {
    return $this->addContextActivity($this->getActivity($activity), 'grouping');
  }

  /**
   * Adds an activity to the category context activities.
   *
   * @param array|XapiActivity|EntityInterface $activity
   *   The object to add as a category context activity.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function addCategoryContext($activity): XapiStatement {
    return $this->addContextActivity($this->getActivity($activity), 'category');
  }

  /**
   * Adds an activity to the other context activities.
   *
   * @param array|XapiActivity|EntityInterface $activity
   *   The object to add as a other context activity.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function addOtherContext($activity): XapiStatement {
    return $this->addContextActivity($this->getActivity($activity), 'other');
  }

  /**
   * Sets the result score on the statement.
   *
   * @param float $raw
   *   The raw score.
   * @param float $max
   *   The maximum score.
   * @param float $min
   *   The minimum score.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function setResultScore(float $raw, float $max = 1, float $min = 0): XapiStatement {
    $score['raw'] = $raw;

    $scaled = $raw / $max;
    if ($scaled >= -1 && $scaled <= 1) {
      $score += [
        'scaled' => $scaled,
        'min' => $min,
        'max' => $max,
      ];
    }

    $this->result['score'] = $score;
    return $this;
  }

  /**
   * Sets the result response.
   *
   * @param string $response
   *   The response value.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function setResultResponse(string $response): XapiStatement {
    $response = trim($response);

    if (empty($response)) {
      return $this;
    }

    $this->result['response'] = $response;
    return $this;
  }

  /**
   * Sets the result completion.
   *
   * @param bool $completed
   *   Whether the object was completed (defaults to true).
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function setResultCompletion(bool $completed = TRUE): XapiStatement {
    $this->result['completion'] = $completed;
    return $this;
  }

  /**
   * Sets the result success.
   *
   * @param bool $success
   *   Whether the attempt was successful (default to true).
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function setResultSuccess(bool $success = TRUE): XapiStatement {
    $this->result['success'] = $success;
    return $this;
  }

  /**
   * Sets the result duration.
   *
   * @param float $duration
   *   The duration of the attempt (in seconds).
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function setResultDuration(float $duration = 0): XapiStatement {
    $this->result['duration'] = sprintf('%s%f%s', 'PT', $duration, 'S');
    return $this;
  }

  /**
   * Appends extensions to the result object.
   *
   * @param array $extensions
   *   Extensions to append to the result.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  public function addResultExtensions(array $extensions): XapiStatement {
    if (!isset($this->result['extensions'])) {
      $this->result['extensions'] = [];
    }

    $this->result['extensions'] += $extensions;

    return $this;
  }

  /**
   * Gets an activity from an arbitrary type.
   *
   * @param array|XapiActivity|EntityInterface $object
   *   The object to generate an activity from.
   *
   * @return array
   *   The activity.
   *
   * @throws InvalidArgumentException
   *   If the provided object cannot be an xAPI activity.
   */
  protected function getActivity($object): XapiActivity {
    if ($object instanceof EntityInterface) {
      $activity = new XapiActivity($this->activityProvider);
      $activity->fromEntity($object);
      return $activity;
    }

    if ($object instanceof XapiActivity) {
      return $object;
    }

    if (is_array($object) && isset($object['id'])) {
      $activity = new XapiActivity($this->activityProvider);
      $activity->setActivity($object);
      return $activity;
    }

    throw new \InvalidArgumentException('An xAPI activity could not be determined from the provided object');
  }

  /**
   * Adds a context activity.
   *
   * @param XapiActivity $contextActivity
   *   The activity to add to the statement context.
   * @param string $contextType
   *   The type of context activity.
   *
   * @return XapiStatement
   *   The current XapiStatement builder.
   */
  protected function addContextActivity(XapiActivity $contextActivity, string $contextType = 'grouping'): XapiStatement {
    if (!isset($this->context['contextActivities']) || !isset($this->context['contextActivities'][$contextType])) {
      $this->context['contextActivities'][$contextType] = [];
    }

    $this->context['contextActivities'][$contextType][] = $contextActivity;
    return $this;
  }

  /**
   * Set a default params to platform property.
   */
  protected function setDefaultPlatform() {
    $this->platform = [
      'name' => $this->configFactory->get('system.site')->get('name'),
      'domain' => \Drupal::request()->getSchemeAndHttpHost(),
      'version' => Settings::get('deployment_identifier', 'dev'),
    ];
  }

  /**
   * Set a key of platform property.
   *
   * Currently we only support the name, platform, domain and version key.
   *
   * @param string $platform_key
   *   The key.
   * @param string $value
   *   The value under the key.
   */
  public function setPlatformValue($platform_key, $value) {
    $this->platform[$platform_key] = $value;
  }

  /**
   * Render the platform array as string.
   *
   * @return string
   *   The platform property in string format.
   */
  public function getPlatformText() {
    return sprintf('%s (%s); %s', $this->platform['name'], $this->platform['version'], $this->platform['domain']);
  }

  /**
   * Returns the class as an json object.
   */
  public function jsonSerialize() {
    $json = [
      'actor' => $this->actor,
      'verb' => $this->verb,
      'object' => $this->object,
      'version' => '1.0.3',
    ];

    if (!empty($this->result)) {
      $json['result'] = $this->result;
    }

    $context = $this->getContext();
    if (!empty($context)) {
      $json['context'] = $context;
    }

    if (isset($this->timestamp)) {
      $json['timestamp'] = $this->timestamp;
    }

    return $json;
  }

}
