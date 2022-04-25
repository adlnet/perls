<?php

namespace Drupal\perls_content_management\Plugin\Action;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Publish moderated or non-moderated content.
 *
 * @Action(
 *   id = "perls_publish_action",
 *   label = @Translation("Publish"),
 *   type = "node",
 *   confirm = FALSE
 * )
 */
class PublishModeratedAction extends ViewsBulkOperationsActionBase implements PluginFormInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * {@inheritdoc}
   * */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * Constructor for Publish Action.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ModerationInformationInterface $moderation_info
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL) {
    if (!$node instanceof NodeInterface) {
      return;
    }
    if ($this->moderationInfo->shouldModerateEntitiesOfBundle($node->getEntityType(), $node->bundle())) {
      $node->set('moderation_state', 'published');
      $node->save();
    }
    else {
      $node->setPublished()->save();
    }

  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return AccessResult::allowedIf($account->hasPermission('use content_moderation transition publish'));
  }

}
