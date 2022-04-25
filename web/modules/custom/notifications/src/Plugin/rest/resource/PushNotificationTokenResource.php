<?php

namespace Drupal\notifications\Plugin\rest\resource;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\notifications\Entity\PushNotificationToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "push_notification_tokens",
 *   label = @Translation("Push Notification Token API"),
 *   uri_paths = {
 *     "canonical" = "/api/push_notification_token",
 *     "create" = "/api/push_notification_token",
 *   }
 * )
 */
class PushNotificationTokenResource extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface|\Symfony\Component\Serializer\Encoder\DecoderInterface
   */
  protected $serializer;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
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
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, SerializerInterface $serializer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->serializer = $serializer;
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
      $container->get('serializer')
    );
  }

  /**
   * Responds to token Delete requests.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function delete(Request $request) {
    $user = \Drupal::currentUser();
    if (!$user) {
      throw new AccessDeniedHttpException('You are not authorized to delete this entity.');
    }
    // Body no longer passed delete method unless it is an entity.
    // We need to pull and deserialize it from the request.
    $data = $request->getContent();
    $type = $request->getContentType();

    try {
      $body = $this->serializer->decode($data, $type, ['request_method' => 'delete']);
    }
    catch (UnexpectedValueException $e) {
      // If an exception was thrown at this stage, there was a problem
      // decoding the data. Throw a 400 http exception.
      throw new BadRequestHttpException($e->getMessage());
    }

    if (!isset($body['token'])) {
      throw new BadRequestHttpException("The body of this request must contain a 'token' field of length less than 256 digits.");
    }
    $device = NULL;
    if (isset($body['device'])) {
      $device = $body['device'];
    }

    // Check to see if this token has already been registered.
    $query = \Drupal::entityQuery('push_notification_token')
      ->condition('value', $body['token'])
      ->condition('auth_user_id', $user->id());

    if (isset($body['device'])) {
      $query->condition('device', $body['device']);
    }
    $ids = $query->execute();
    if (empty($ids)) {
      throw new BadRequestHttpException("This token does not exists.");
    }
    $push_token_entity = \Drupal::entityTypeManager()->getStorage('push_notification_token')->load(reset($ids));
    $push_token_entity->delete();

    return new ModifiedResourceResponse(NULL, 204);
  }

  /**
   * Responds to token POST request.
   *
   * @param array $data
   *   The body of the request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function post(array $data) {
    $user = \Drupal::currentUser();
    if (!$user) {
      throw new AccessDeniedHttpException('You are not authorized to update this entity.');
    }
    if (!isset($data['token'])) {
      throw new BadRequestHttpException("The body of this request must contain a 'token' field of length less than 256 digits.");
    }
    $token = $data['token'];
    // Check for other fields.
    $device = NULL;
    if (isset($data['device'])) {
      $device = $data['device'];
    }

    // Check to see if this token has already been registered.
    $query = \Drupal::entityQuery('push_notification_token')
      ->condition('status', 1)
      ->condition('value', $token);
    $ids = $query->execute();
    $push_token_entity = NULL;
    if (empty($ids)) {
      // Create the entity.
      $push_token_entity = PushNotificationToken::create([
        'value' => $token,
        'device' => $device,
        'status' => TRUE,
      ]);
      $push_token_entity->save();
    }
    else {
      // Load the entity.
      $push_token_entity = \Drupal::entityTypeManager()->getStorage('push_notification_token')->load(reset($ids));
      $push_token_entity->set('auth_user_id', $user->id());
      $push_token_entity->set('device', $device);
      $push_token_entity->save();
    }

    return new ModifiedResourceResponse($push_token_entity, 201);
  }

}
