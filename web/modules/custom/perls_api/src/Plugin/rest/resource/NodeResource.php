<?php

namespace Drupal\perls_api\Plugin\rest\resource;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\rest\resource\EntityResource;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Overrides Node entities resource plugin.
 *
 * @see \Drupal\rest\Plugin\Deriver\EntityDeriver
 *
 * @RestResource(
 *   id = "node",
 *   label = @Translation("Entity"),
 *   serialization_class = "Drupal\Core\Entity\Entity",
 *   deriver = "Drupal\rest\Plugin\Deriver\EntityDeriver",
 *   uri_paths = {
 *     "canonical" = "/node/{entity}",
 *     "create" = "/node"
 *   }
 * )
 */
class NodeResource extends EntityResource implements DependentPluginInterface {

  /**
   * Overrides POST requests and saves the new entity with group field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function post(EntityInterface $entity = NULL) {
    if ($entity == NULL) {
      throw new BadRequestHttpException('No entity content received.');
    }

    $entity_access = $entity->access('create', NULL, TRUE);
    if (!$entity_access->isAllowed()) {
      throw new AccessDeniedHttpException($entity_access->getReason() ?: $this->generateFallbackAccessDeniedMessage($entity, 'create'));
    }
    $definition = $this->getPluginDefinition();
    // Verify that the deserialized entity is of the type that we expect to
    // prevent security issues.
    if ($entity->getEntityTypeId() !== $definition['entity_type']) {
      throw new BadRequestHttpException('Invalid entity type');
    }

    $exception = new BadRequestHttpException('Only new entities can be created');
    if (!$entity->isNew() && $entity->hasField('entitygroupfield') && !empty($entity->get('entitygroupfield')->getValue())) {
      // Ignore if entity has groups, since the entity will already be saved
      // in LearnLinkEntityNormalizer.
    }
    elseif (!$entity->isNew()) {
      throw $exception;
    }

    $this->checkEditFieldAccess($entity);

    // Validate the received data before saving.
    $this->validate($entity);
    try {
      $entity->save();
      $this->logger->notice('Created entity %type with ID %id.', [
        '%type' => $entity->getEntityTypeId(),
        '%id' => $entity->id(),
      ]);

      // 201 Created responses return the newly created entity in the response
      // body. These responses are not cacheable, so we add no cacheability
      // metadata here.
      $headers = [];
      if (in_array('canonical', $entity->uriRelationships(), TRUE)) {
        $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE);
        $headers['Location'] = $url->getGeneratedUrl();
      }
      return new ModifiedResourceResponse($entity, 201, $headers);
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
  }

  /**
   * Override patch to enable revisions.
   *
   * @inheritDoc
   */
  public function patch(EntityInterface $original_entity, EntityInterface $entity = NULL) {
    if ($original_entity->getEntityType()->isRevisionable()) {
      // Create a new revision for PATCH requests.
      $original_entity->setNewRevision();
      $original_entity->setRevisionLogMessage($this->t("Updated by the REST API."));
    }
    return parent::patch($original_entity, $entity);
  }

}
