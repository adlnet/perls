<?php

namespace Drupal\xapi;

use Drupal\user\UserInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides an xApi actor object.
 *
 * @package Drupal\xapi
 */
class XapiActor implements \JsonSerializable {

  /**
   * The user display name.
   *
   * @var string
   */
  protected $name;

  /**
   * Actor ifi.
   *
   * @var array
   */
  protected $ifi = [];

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * An IFI plugin to determine how to represent the actor.
   *
   * @var \Drupal\xapi\XapiActorIFIManager
   */
  protected $ifiManager;

  /**
   * Constructs a new XapiActor builder.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param XapiActorIFIManager $ifi_manager
   *   The current IFI manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    XapiActorIFIManager $ifi_manager
  ) {
    $this->configFactory = $config_factory;
    $this->ifiManager = $ifi_manager;
  }

  /**
   * Creates a new XapiActor builder.
   *
   * @return XapiActor
   *   The XapiActor builder.
   */
  public static function create(): XapiActor {
    return new self(
      \Drupal::configFactory(),
      \Drupal::service('plugin.manager.xapi_actor_ifi'),
    );
  }

  /**
   * Generates an actor object from a Drupal user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user.
   *
   * @return XapiActor
   *   An XapiActor builder.
   */
  public static function createWithUser(UserInterface $user): XapiActor {
    $actor = self::create();
    return $actor->fromUser($user);
  }

  /**
   * Populates the actor from a Drupal user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user.
   *
   * @return XapiActor
   *   The current XapiActor builder.
   */
  public function fromUser(UserInterface $user): XapiActor {
    $config = $this->configFactory->get('xapi.settings');
    $this->name = $config->get('real_name') ? $user->getDisplayName() : NULL;
    $this->ifi = $this->ifiManager->getActiveIfi($user);
    return $this;
  }

  /**
   * Sets the display name of the actor.
   *
   * @param string|null $name
   *   The display name.
   *
   * @return XapiActor
   *   The current XapiActor builder.
   */
  public function setName(?string $name): XapiActor {
    $this->name = $name;
    return $this;
  }

  /**
   * Sets the IFI to an email address.
   *
   * @param string $email
   *   The email address.
   *
   * @return XapiActor
   *   The current XapiActor builder.
   */
  public function setEmail(string $email): XapiActor {
    return $this->setMbox('mailto:' . $email);
  }

  /**
   * Sets the IFI to an mbox.
   *
   * @param string $mbox
   *   The mbox value; must be prefixed with "mailto:".
   *
   * @return XapiActor
   *   The current XapiActor builder.
   */
  public function setMbox(string $mbox): XapiActor {
    $this->ifi = [
      'mbox' => $mbox,
    ];

    return $this;
  }

  /**
   * Sets an account as the actor IFI.
   *
   * @param string $name
   *   The account name.
   * @param string|null $homepage
   *   The canonical homepage; assumed to be the current site.
   *
   * @return XapiActor
   *   The current XapiActor builder.
   */
  public function setAccount(string $name, ?string $homepage = NULL): XapiActor {
    global $base_url;

    $this->ifi = [
      'account' => [
        'homePage' => $homepage ?? $base_url,
        'name' => $name,
      ],
    ];

    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function jsonSerialize() {
    $actor = [
      'objectType' => 'Agent',
    ];

    if (!empty($this->name)) {
      $actor['name'] = $this->name;
    }

    return $actor + $this->ifi;
  }

}
