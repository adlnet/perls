<?php

use Behat\Mink\Exception\ExpectationException;
use NuvoleWeb\Drupal\DrupalExtension\Context\ContentContext;
use Webmozart\Assert\Assert;

class PerlsDrupalContentContext extends ContentContext {

  /**
   * {@inheritdoc}
   */
  protected function visitContentPage($op, $type, $title) {
    $nid = $this->getEntityIdByLabel('node', $type, $title);
    $path = [
      'view' => "node/$nid",
      'edit' => "node/$nid/edit",
      'delete' => "node/$nid/delete",
    ];
    $this->visitPath($path[$op]);
  }

  /**
   * Ovverrides the getEntityIdByLabel function from Drupal 8 driver.
   */
  public function getEntityIdByLabel($entity_type, $bundle, $label) {
    /** @var \Drupal\node\NodeStorage $storage */
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $type = $storage->getEntityType();

    if ($type->hasKey('label')) {
      $label_key = $type->getKey('label');
    }
    else {
      // Fall back to the name field (for users for example) when the entity
      // type has no label key.
      $label_key = 'name';
    }

    $query = \Drupal::entityQuery($entity_type)->accessCheck(FALSE);
    if ($bundle) {
      $bundle_key = $type->getKey('bundle');
      $query->condition($bundle_key, $bundle);
    }
    $query->condition($label_key, $label);
    $query->range(0, 1);

    $result = $query->execute();
    Assert::notNull($result, __METHOD__ . ": No Entity {$entity_type} with name {$label} found.");
    return current($result);
  }

  /**
   * Generate fake contents.
   *
   * @Given Generate :number of :content_type content.
   */
  public function generateFakeDevelContent($number, $content_type) {
    if (!\Drupal::service('module_handler')->moduleExists('devel_generate')) {
      throw new ExpectationException('You need enable the devel_generate!', $this->getSession());
    }

    /** @var \Drupal\devel_generate\Plugin\DevelGenerate\ContentDevelGenerate $devel_generate */
    $devel_generate = \Drupal::service('plugin.manager.develgenerate')->createInstance('content', []);

    $devel_generate->generate([
      'num' => $number,
      'max_comments' => 0,
      'node_types' => [
        $content_type => $content_type,
      ],
      'kill' => 0,
      'authors' => [
        1 => 1,
      ],
      'add_type_label' => FALSE,
      'title_length' => '10',
      'add_language' => [
        'en' => 'en',
      ],

    ]);
  }

}
