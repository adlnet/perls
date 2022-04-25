<?php

namespace Drupal\perls_api\Plugin\rest\resource;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\entity_normalization\EntityNormalizationManagerInterface;
use Drupal\user\Entity\User;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get the currently authenticated user.
 *
 * @RestResource(
 *   id = "current_user_resource",
 *   label = @Translation("Current User Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/user/me"
 *   }
 * )
 */
class CurrentUserResource extends ResourceBase {

  /**
   * Logged in user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The drupal entity repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Entity normalizer manager.
   *
   * @var \Drupal\serialization\Normalizer\NormalizerBase
   */
  protected $normalizerManager;

  /**
   * Service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * Constructs a user resource.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Logged in user.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   Drupal entity repository.
   * @param \Drupal\entity_normalization\EntityNormalizationManagerInterface $normalizer_manager
   *   The normalizer manager.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Drupal service container.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    EntityRepositoryInterface $entity_repository,
    EntityNormalizationManagerInterface $normalizer_manager,
    ContainerInterface $container) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->entityRepository = $entity_repository;
    $this->normalizerManager = $normalizer_manager;
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user'),
      $container->get('entity.repository'),
      $container->get('entity_normalization.manager'),
      $container
    );
  }

  /**
   * Gets the current user.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The current user entity.
   */
  public function get() {
    $user = User::load(\Drupal::currentUser()->id());
    $response = new ResourceResponse($user);
    $response->addCacheableDependency($user);
    $response->getCacheableMetadata()->addCacheContexts(['user']);
    return $response;
  }

  /**
   * Update the user data.
   *
   * @param array $data
   *   The posted user data.
   */
  public function patch(array $data) {
    $message = [];
    $status_code = 200;
    if (!isset($data['id']) && !Uuid::isValid($data['id'])) {
      $message = 'User id is missing or incorrect';
      $status_code = 404;
    }

    $user = $this->entityRepository->loadEntityByUuid('user', $data['id']);

    if (!isset($user)) {
      $message = 'User does not exist';
      $status_code = 404;
    }

    if (isset($user) && $data['goals']) {
      $this->updateUserGoals($data['goals'], $user);
    }

    if (!empty($message)) {
      $message = ['message' => $message];
    }

    return new ModifiedResourceResponse($message, $status_code);
  }

  /**
   * Gets a field mapping between api field and drupal fields.
   *
   * This function use the entity normalizer yml file to get the original drupal
   * field names. The retuning array contains the normalizer of the field values
   * if it's available.
   *
   * @param \Drupal\Core\Entity\EntityInterface $user
   *   A drupal user.
   *
   * @return array|null
   *   A field name list which keyed by api field names.
   */
  protected function getNormalizedFieldNames(EntityInterface $user) {
    $field_name = [];
    $entity_config = $this->normalizerManager->getEntityConfig($user);
    if (!$entity_config) {
      return NULL;
    }
    $fields = $entity_config->getFields();
    /** @var \Drupal\entity_normalization\FieldConfig $field_config */
    foreach ($fields as $drupal_field_name => $field_config) {
      $field_name[$field_config->getName()] = [
        'field_name' => $drupal_field_name,
        'normalizer' => $field_config->getNormalizerName(),
      ];
    }

    return $field_name;
  }

  /**
   * Update user's goal field through api.
   *
   * @param array $goals
   *   A list of goal fields with values.
   * @param \Drupal\Core\Entity\EntityInterface $user
   *   A drupal user.
   */
  protected function updateUserGoals(array $goals, EntityInterface $user) {
    $field_list = $this->getNormalizedFieldNames($user);
    foreach ($goals as $field_name => $field_value) {
      if ($user->hasField($field_list[$field_name]['field_name'])) {
        if (!empty($field_list[$field_name]['normalizer'])) {
          /** @var \Drupal\serialization\Normalizer\NormalizerBase $normalizer */
          $normalizer = $this->container->get($field_list[$field_name]['normalizer']);
          if (method_exists($normalizer, 'supportsDenormalization') && $normalizer->supportsDenormalization($field_value, gettype($field_value))) {
            $context = [];
            $context['drupal_field_name'] = $field_list[$field_name]['field_name'];
            $context['entity_type'] = $user->getEntityTypeId();
            $context['bundle'] = $user->bundle();
            $user->set($field_list[$field_name]['field_name'], $normalizer->denormalize($field_value, gettype($field_value), NULL, $context));
          }
        }
        else {
          $user->set($field_list[$field_name]['field_name'], $field_value);
        }
      }
    }

    $user->save();
  }

}
