<?php

namespace Drupal\perls_api\EntityNormalizer;

use Drupal\node\Entity\Node;
use Drupal\xapi\Plugin\Field\FieldType\XapiContentFileItem;
use Drupal\xapi\XapiActivity;
use Drupal\xapi\XapiContentFileHelper;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer to turn an entity into it's rendered form.
 */
class PerlsLearnPackageUrlNormalizer implements NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $field_value */
    $field_value = $data->get('field_learning_package');
    try {
      // Currently this normalizer doesn't handle multi values fields.
      $field_item = $field_value->get(0);
      if ($field_item instanceof XapiContentFileItem) {
        $launch_url = XapiContentFileHelper::getLaunchUrl($field_item);
        $query = $launch_url->getOption('query');
        $query['activity_id'] = XapiActivity::createFromEntity($data)->getId();
        $launch_url->setOption('query', $query);
        return $launch_url->toString();
      }
    }
    catch (\InvalidArgumentException $exception) {
      // Here we want only prevent to fail if the field is empty.
      return NULL;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return ($data instanceof Node) && ($data->hasField('field_learning_package'));
  }

}
