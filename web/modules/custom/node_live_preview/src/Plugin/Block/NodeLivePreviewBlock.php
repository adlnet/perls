<?php

namespace Drupal\node_live_preview\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'NodeLivePreviewBlock' block.
 *
 * @Block(
 *   id = "live_preview_block",
 *   admin_label = @Translation("Live Preview Block"),
 *   category = @Translation("Content")
 * )
 */
class NodeLivePreviewBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity view builder interface.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  private $viewBuilder;

  /**
   * The node interface.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $node;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a NodeBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityDisplayRepositoryInterface $entity_display_repository, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->viewBuilder = $entity_type_manager->getViewBuilder('node');

    // If on add page, try to get node from Node Id
    // in config for this content type.
    if (\Drupal::routeMatch()->getRouteName() == 'node.add') {
      $node_type = \Drupal::routeMatch()->getParameter('node_type')->id();
      if (isset($configuration['nid_' . $node_type])) {
        $this->node = $entity_type_manager->getStorage('node')->load($configuration['nid_' . $node_type]);
      }
    }
    else {
      $this->node = \Drupal::routeMatch()->getParameter('node');

    }

    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Retrieve existing configuration for this block.
    $config = $this->getConfiguration();

    // Add form fields to existing block configuration form.
    // Node IDs.
    $node_types = NodeType::loadMultiple();
    foreach ($node_types as $node_type) {
      $node_type_id = $node_type->id();
      $node_type_label = $node_type->label();
      $form['nid_' . $node_type_id] = [
        '#title' => $node_type_label . ' Node to display',
        '#description' => 'The ' . $node_type_label . ' node you want to display',
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_handler' => 'default',
      ];
      if (isset($config['nid_' . $node_type_id]) && !empty($config['nid_' . $node_type_id])) {
        $form['nid_' . $node_type_id]['#default_value'] = Node::load($config['nid_' . $node_type_id]);
      }
    }

    // View modes.
    $options = [];
    $view_modes = $this->entityDisplayRepository->getAllViewModes();
    if (isset($view_modes['node'])) {
      foreach ($view_modes['node'] as $view_mode => $view_mode_info) {
        $options[$view_mode] = $view_mode_info['label'];
      }
    }

    $form['view_mode'] = [
      '#title' => t('View mode'),
      '#description' => t('Select the view mode you want your node to render in.'),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => (isset($config['view_mode']) && !empty($config['view_mode']) ? $config['view_mode'] : 'full'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $node_types = NodeType::loadMultiple();
    foreach ($node_types as $node_type) {
      $node_type_id = $node_type->id();
      $this->configuration['nid_' . $node_type_id] = $form_state->getValue('nid_' . $node_type_id);
    }
    $this->configuration['view_mode'] = $form_state->getValue('view_mode');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#markup' => $this->t('View Preview Here'),
    ];
    $config = $this->getConfiguration();
    if (!$this->node instanceof NodeInterface) {
      return $build;
    }
    $view_mode = (isset($config['view_mode']) && !empty($config['view_mode']) ? $config['view_mode'] : 'full');

    // Use full mode for Learn Articles.
    if ($this->node->getType() == 'learn_article' || $this->node->getType() == 'podcast') {
      $view_mode = 'full';
    }
    $build = $this->viewBuilder->view($this->node, $view_mode);

    // Attach JS library to block.
    $build['#attached']['library'][] = 'node_live_preview/node_live_preview-lib';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
