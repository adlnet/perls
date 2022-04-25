<?php

namespace Drupal\perls_group_management\PathProcessor;

use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Changed the outbound path of group related path.
 */
class GroupOutboundPathProcessor implements OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    if (isset($options['route']) && $options['route']->getPath() === '/group/{group}/leave') {
      $leave_destination = Url::fromRoute('page_manager.page_view_groups_groups-block_display-0')->toString();
      $options['query']['destination'] = $leave_destination;
    }
    return $path;
  }

}
