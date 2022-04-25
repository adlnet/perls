/**
 * @file
 * Custom JS for admin forms.
 */

(function($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.PerlsForms = {
    attach: function(context, settings) {
      $('.vbo-view-form, .views-form', context).once('perls-vbo-init').each(function (i, form) {
        attachToBulkForm(form);
      });
    },
  };

  /**
   * Attaches to a form that represents bulk operations.
   * 
   * @param {HTMLFormElement} form
   *   The bulk form to attach to.
   */
  function attachToBulkForm(form) {
    updateButtonStatus(form);
    $('input:checkbox', form).on('change', function(e) {
      // When the user toggles the select all button, it may take a _moment_
      // for all the checkboxes to be in their new state.
      setTimeout(function() {
        updateButtonStatus(form);
      }, 1);
    });
  }

  /**
   * Updates the submit button on form actions based on the selected items.
   * 
   * @param {*} context
   *   The context.
   */
  function updateButtonStatus(context) {
    // If the context doesn't have any checkboxes, then there's nothing to do.
    if ($('input:checkbox', context).length === 0) {
      return;
    }

    var isEmptySelection = $('input:checkbox:checked', context).length === 0;
    $('.form-actions .form-submit', context).prop('disabled', isEmptySelection);
  }

})(jQuery, Drupal, drupalSettings);
