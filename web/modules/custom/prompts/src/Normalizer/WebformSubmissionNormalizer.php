<?php

namespace Drupal\prompts\Normalizer;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformTokenManagerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * Normalizer to normalize the webform submission.
 */
class WebformSubmissionNormalizer extends NormalizerBase implements NormalizerAwareInterface {

  use NormalizerAwareTrait;

  /**
   * Webform token manager service.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * Constructor of webform submission normalizer.
   *
   * @param \Drupal\webform\WebformTokenManagerInterface $token_manager
   *   Webform token manager.
   */
  public function __construct(WebformTokenManagerInterface $token_manager) {
    $this->tokenManager = $token_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = WebformSubmission::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $result = [];

    /** @var \Drupal\webform\Entity\WebformSubmission $object */
    $result['uuid'] = $object->uuid();
    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = $object->getWebform();
    $result['form'] = [];
    $webform_elements = $webform->getElementsDecodedAndFlattened();
    if (!empty($webform_elements)) {
      foreach ($webform_elements as $element) {
        // I should add this $bubbleable_metadata otherwise I will get too
        // early rendering error in my endpoint.
        $bubbleable_metadata = new BubbleableMetadata();
        $bubbleable_metadata->addCacheableDependency($object);
        $title = $this->tokenManager->replace([$element['#title']], $object, [], [], $bubbleable_metadata);
        $result['form']['questions'][] = [
          'required' => $element['#required'],
          'question' => $title,
          'type' => $element['#type'],
          'options' => $element['#options'],
        ];
      }
    }

    return $result;
  }

}
