<?php

namespace Drupal\perls_recommendation\Recommendation;

use Drupal\node\NodeInterface;

/**
 * A recommendation object.
 */
class Recommendation {
  /**
   * The node id of the recommendation.
   *
   * @var int
   */
  public $id;
  /**
   * The node of the recommendation.
   *
   * @var Drupal\node\NodeInterface
   */
  public $node;
  /**
   * The node type of the recommendation.
   *
   * @var string
   */
  public $type;
  /**
   * The recommendation weight.
   *
   * The higher the number the more strongly this is recommended.
   *
   * @var float
   */
  public $weight;
  /**
   * The reason this content was recommended.
   *
   * @var string
   */
  public $recommendationReason;
  /**
   * The recommendation source.
   *
   * @var string
   */
  public $recommendationSource;

  /**
   * Create a new recommendation object.
   *
   * @param int $id
   *   The node id of the content being recommended.
   * @param Drupal\node\NodeInterface $node
   *   The Node object.
   * @param string $type
   *   The node type of the recommended content.
   * @param float $weight
   *   The weight of the recommendation. Higher more strongly recommended.
   * @param string $recommendationReason
   *   A reason why this content is being recommended.
   * @param string $recommendationSource
   *   The Plugin id that is recommending this content.
   */
  public function __construct($id, NodeInterface $node, $type, $weight, $recommendationReason, $recommendationSource) {
    $this->id = $id;
    $this->node = $node;
    $this->type = $type;
    $this->weight = $weight;
    $this->recommendationReason = $recommendationReason;
    $this->recommendationSource = $recommendationSource;
  }

}
