<?php

namespace Drupal\perls_learner_browse\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Block\BlockManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides Block via Ajax.
 */
class BlockViaAjaxController extends ControllerBase {

  /**
   * The Block manager.
   *
   * @var \Drupal\Core\Block\BlockManager
   */
  protected $blockManager;

  /**
   * NodeNextCourseController constructor.
   *
   * @param \Drupal\Core\Block\BlockManager $blockManager
   *   BlockManager manager.
   */
  public function __construct(BlockManager $blockManager) {
    $this->blockManager = $blockManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block')
    );
  }

  /**
   * Render the block.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response containing block.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function renderBlock(Request $request) {
    $response = new AjaxResponse();

    // Get block by plugin, configs and selector.
    $data = $request->query->get('data');
    $decodedData = json_decode($data, TRUE);

    if (!empty($decodedData)) {
      $plugin_block = $this->blockManager->createInstance($decodedData['plugin_id'], $decodedData['config']);
      $render = $plugin_block->build();

      if ($render) {
        // Inserts block.
        $response->addCommand(new AppendCommand($decodedData['selector'], $render));

        return $response;
      }
    }
    return $response;
  }

}
