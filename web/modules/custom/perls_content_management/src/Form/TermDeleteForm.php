<?php

namespace Drupal\perls_content_management\Form;

use Drupal\Core\Url;
use Drupal\taxonomy\Form\TermDeleteForm as CoreTermDeleteForm;

/**
 * Provides a deletion confirmation form for taxonomy term.
 *
 * @internal
 */
class TermDeleteForm extends CoreTermDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the @type %label?', [
      '@type' => $this->getEntity()->vid->entity->label(),
      '%label' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    // Determine how many nodes are still associated with this term.
    $query = \Drupal::database()
      ->select('taxonomy_index', 'ti')
      ->fields('ti', ['nid'])
      ->condition('ti.tid', $this->entity->id())
      ->distinct(TRUE)
      ->countQuery();
    $count = $query->execute()->fetchField();
    if ($count == 0) {
      return $this->t('This @type is not used. It can safely be removed.', [
        '@type' => strtolower($this->getEntity()->vid->entity->label()),
      ]);
    }
    return $this->formatPlural($count, '<strong>This @type will be removed from one item.</strong> This action cannot be undone.', '<strong>This @type will be removed from @count items.</strong> This action cannot be undone.', [
      '@type' => strtolower($this->getEntity()->vid->entity->label()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    return $this->t('Deleted @type %name.', [
      '@type' => $this->getEntity()->vid->entity->label(),
      '%name' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    // The cancel URL is the vocabulary collection, terms have no global
    // list page.
    return Url::fromRoute('view.manage_vocabularies.page_1', ['taxonomy_vocabulary' => $this->getEntity()->bundle()]);
  }

}
