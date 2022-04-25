<?php

namespace Drupal\notifications\Form;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\Views;

/**
 * Defines common methods for Views Bulk Operations forms.
 */
trait ViewsBulkOperationsFormTrait {
  use StringTranslationTrait;

  /**
   * Retrieves a summary string of the current form selection.
   */
  protected function getSelectionSummary(array $form_data): string {
    if (!empty($form_data['view_id'])) {
      $view = Views::getView($form_data['view_id']);
      $type = $view->getBaseEntityType();

      if ($type) {
        return $type->getCountLabel($form_data['selected_count']);
      }
    }

    return $this->formatPlural($form_data['selected_count'], '1 entity', '@count entities');
  }

}
