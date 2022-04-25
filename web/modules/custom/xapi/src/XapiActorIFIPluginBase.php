<?php

namespace Drupal\xapi;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class can be used as a base for all Xapi actor ifi type plugins.
 */
abstract class XapiActorIFIPluginBase extends PluginBase implements ContainerFactoryPluginInterface, XapiActorIFIInterface {

  /**
   * Drupal current user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $currentUser;

  /**
   * Xapi statement creator service.
   *
   * @var \Drupal\xapi_reporting\XapiStatementCreator
   */
  protected $statementCreator;

  /**
   * Drupal user storage manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userManager;

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current drupal user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   User storage manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->userManager = $entity_type_manager->getStorage('user');
    $this->currentUser = $this->userManager->load($current_user->id());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->configuration['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->configuration['description'];
  }

}
