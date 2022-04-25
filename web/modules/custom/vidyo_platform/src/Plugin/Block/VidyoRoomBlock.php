<?php

namespace Drupal\vidyo_platform\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\vidyo_platform\Api\VidyoApiException;
use Drupal\vidyo_platform\Api\Model\Room;
use Drupal\vidyo_platform\Api\VidyoApiRequestException;
use Drupal\vidyo_platform\Plugin\VidyoRoomRendererInterface;
use Drupal\vidyo_platform\VidyoRoomInfoTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for accessing a Vidyo room.
 *
 * @Block(
 *  id = "vidyo_room_block",
 *  admin_label = @Translation("Meeting Room"),
 * )
 */
class VidyoRoomBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use VidyoRoomInfoTrait;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Drupal\vidyo_platform\VidyoRoomManager definition.
   *
   * @var \Drupal\vidyo_platform\VidyoRoomManager
   */
  protected $roomManager;

  /**
   * Drupal\Core\State\StateInterface definition.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Manages plugins that can render a Vidyo room.
   *
   * @var \Drupal\vidyo_platform\Plugin\VidyoRoomRendererPluginManager
   */
  protected $roomRendererManager;

  /**
   * The Vidyo room currently associated with this block.
   *
   * @var \Drupal\vidyo_platform\Api\Model\Room
   */
  protected $currentRoom;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.channel.vidyo_platform');
    $instance->messenger = $container->get('messenger');
    $instance->stringTranslation = $container->get('string_translation');
    $instance->roomManager = $container->get('vidyo_platform.rooms');
    $instance->state = $container->get('state');
    $instance->currentUser = $container->get('current_user');
    $instance->roomRendererManager = $container->get('plugin.manager.vidyo_room_renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      // Unable to use dependency injection here because
      // `defaultConfiguration` is invoked by the constructor.
      'uuid' => \Drupal::service('uuid')->generate(),
      'room_availability' => 'unlocked',
      'offline_message' => $this->t('Sorry, this room is unavailable right now.'),
      'renderer' => 'vidyo_app',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $rendering_options = $this->roomRendererManager->getOptions(TRUE);
    $form['renderer'] = [
      '#type' => count($rendering_options) > 1 ? 'radios' : 'value',
      '#required' => TRUE,
      '#title' => $this->t('Room Appearance'),
      '#options' => $rendering_options,
      '#default_value' => $this->configuration['renderer'],
    ];

    $form['room_availability'] = [
      '#type' => 'radios',
      '#title' => $this->t('Room Availability'),
      '#description' => [
        '#theme' => 'item_list',
        '#prefix' => $this->t('Select what determines whether users are given access to the room.'),
        '#items' => [
          'exists' => $this->t('"Always" - Users can join the room whenever they want.'),
          'unlocked' => $this->t('"Room is unlocked" - Users can join the room only if it is unlocked.'),
          'occupied' => $this->t('"Room has occupants" - Users can join the room only if someone else (i.e. a moderator) is already in the room.'),
        ],
      ],
      '#options' => [
        'exists' => $this->t('Always'),
        'unlocked' => $this->t('Room is unlocked'),
        'occupied' => $this->t('Room has occupants'),
      ],
      '#default_value' => $this->configuration['room_availability'],
    ];

    $form['offline_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Offline message'),
      '#description' => $this->t('Displayed to users when the room is unavailable.'),
      '#default_value' => $this->configuration['offline_message'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['renderer'] = $form_state->getValue('renderer');
    $this->configuration['room_availability'] = $form_state->getValue('room_availability');
    $this->configuration['offline_message'] = $form_state->getValue('offline_message');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $room = NULL;
    $build = [
      '#cache' => [
        'contexts' => ['user.permissions'],
        'max-age' => 5,
      ],
    ];

    try {
      $room = $this->getRoom();
    }
    catch (VidyoApiException $e) {
      $this->messenger->addWarning($this->t('A problem occurred while trying to connect to Vidyo.'));
    }

    if (!$room || !$this->isRoomAvailable($room)) {
      $build['offline'] = [
        '#theme' => 'vidyo_offline',
        '#room' => $room,
        '#offline_message' => $this->configuration['offline_message'],
      ];
    }
    else {
      $build['room'] = $this->getRenderer()->renderRoom($room, $this->currentUser);
    }

    if ($this->canModerateRoom($room)) {
      try {
        $url = $this->roomManager->getModeratorUrl($room);

        $build['manage'] = [
          '#type' => 'details',
          '#open' => TRUE,
          '#title' => $this->t('Manage Room'),
          'moderation' => [
            '#type' => 'item',
            '#title' => $this->t('Moderate Room'),
            '#description' => $this->t('Launch moderation controls to manage the room participants.'),
            '#description_display' => 'after',
            'content' => [
              '#type' => 'link',
              '#title' => $this->t('Open Moderation'),
              '#url' => Url::fromUri($url, ['attributes' => ['target' => '_blank']]),
              '#attributes' => [
                'class' => ['button', 'button--action'],
              ],
            ],
          ],
          'status' => [
            '#type' => 'item',
            '#title' => $this->t('Room Status'),
            '#description' => $this->t('When a room is locked, no new participants can join the room.'),
            '#description_display' => 'after',
            'content' => $this->renderRoomStatus($room),
          ],
          'url' => [
            '#type' => 'item',
            '#title' => $this->t('Room URL'),
            '#description' => $this->t('Use this URL to join via the Vidyo app. Users will be required to use an access code (below) to authenticate themselves.'),
            '#description_display' => 'after',
            'content' => [
              '#type' => 'link',
              '#title' => $room->getUrl(),
              '#url' => Url::fromUri($room->getUrl(), ['attributes' => ['target' => '_blank']]),
            ],
          ],
          'pin' => [
            '#type' => 'item',
            '#title' => $this->t('Access Code'),
            '#description' => $this->t('The access code is only required if participants use the Vidyo app to join the room.'),
            '#description_display' => 'after',
            '#markup' => $room->getPin(),
          ],
        ];
      }
      catch (VidyoApiException $e) {
        $this->messenger->addWarning($this->t('A problem occurred while generating a moderator URL.'));
      }
    }

    return $build;
  }

  /**
   * Gets the room associated with this block.
   *
   * @return \Drupal\vidyo_platform\Api\Model\Room|null
   *   The room associated with this block.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if there was a problem accessing the Vidyo room.
   */
  public function getRoom(): ?Room {
    if ($this->currentRoom) {
      return $this->currentRoom;
    }

    $room_key = $this->getRoomKey();
    if (!$room_key) {
      return $this->createRoom();
    }

    try {
      $this->currentRoom = $this->roomManager->getRoomByKey($room_key);
    }
    catch (VidyoApiException $e) {
      if ($e->getCode() === 401 || ($e instanceof VidyoApiRequestException && $e->getFaultType() === 'InvalidArgumentFault')) {
        $this->logger->notice('The room key %key is no longer valid so a new room is being created.', [
          '%key' => $room_key,
        ]);
        return $this->createRoom();
      }

      $this->logger->error('Unable to connect to room using key %key: %error', [
        '%key' => $room_key,
        '%error' => $e->getMessage(),
      ]);
      throw $e;
    }

    return $this->currentRoom;
  }

  /**
   * Retrieves the renderer to use for rendering the Vidyo room in this block.
   *
   * @return \Drupal\vidyo_platform\Plugin\VidyoRoomRendererInterface
   *   The renderer.
   */
  protected function getRenderer(): VidyoRoomRendererInterface {
    $plugin = $this->configuration['renderer'] ?? 'vidyo_app';
    return $this->roomRendererManager->createInstance($plugin);
  }

  /**
   * Creates a room.
   *
   * @return \Drupal\vidyo_platform\Api\Model\Room
   *   The newly created room.
   *
   * @throws \Drupal\vidyo_platform\Api\VidyoApiException
   *   Thrown if the room was not able to be created.
   */
  protected function createRoom(): Room {
    try {
      $room = $this->roomManager->createAdhocRoom();
      $this->currentRoom = $room;
      $this->setRoomKey($room->getRoomKey());
      return $room;
    }
    catch (VidyoApiException $e) {
      $this->logger->error('Encountered an error while creating a Vidyo room: %error', ['%error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Determines whether a user is allowed to moderate a Vidyo room.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room|null $room
   *   The room.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user; defaults to the current user.
   *
   * @return bool
   *   TRUE if the user is allowed to moderate the room.
   */
  protected function canModerateRoom(?Room $room, ?AccountInterface $account = NULL): bool {
    if (!$room) {
      return FALSE;
    }

    if (!$account) {
      $account = $this->currentUser;
    }

    return $account->hasPermission('administer vidyo rooms') || $account->hasPermission('moderate any vidyo room');
  }

  /**
   * Determines whether the room is currently available.
   *
   * @param \Drupal\vidyo_platform\Api\Model\Room $room
   *   The room to check availability on.
   *
   * @return bool
   *   TRUE if the room is available for participants to join.
   */
  protected function isRoomAvailable(Room $room): bool {
    switch ($this->configuration['room_availability']) {
      case 'exists':
        return TRUE;

      case 'unlocked':
        return $room->isUnlocked();

      case 'occupied':
        return $room->hasOccupants();

      default:
        return FALSE;
    }
  }

  /**
   * Set a room key to associated with this block.
   *
   * @param string $key
   *   The room key.
   */
  protected function setRoomKey(string $key) {
    return $this->state->set($this->getStateKey(), $key);
  }

  /**
   * Gets the room key associated with this block (if it exists).
   *
   * @return string|null
   *   The room key.
   */
  protected function getRoomKey(): ?string {
    return $this->state->get($this->getStateKey());
  }

  /**
   * Retrieves the key for storing/retrieving information from state.
   *
   * @return string
   *   The state key.
   */
  protected function getStateKey(): string {
    return 'vidyo_platform:block[' . $this->configuration['uuid'] . ']';
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access vidyo rooms');
  }

}
