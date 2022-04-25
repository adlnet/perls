/**
 * @file
 * Main module JS file.
 */

(function (Drupal, $) {
  'use strict';
  Drupal.behaviors.mediaUnsplash = {
    attach: function () {
      var eventType = 'click';
      var cardinality = 1;
      if (window.PointerEvent) {
        eventType = 'pointerup';
      }
      var submitButton = '#unsplash-submit-entities';
      var imageWrapper = '.unsplash-image-wrapper';
      $(document).on(eventType, imageWrapper, function () {
        // Always allow user to unselect items.
        if ($(this).hasClass('unsplash-selected-image')) {
          $(this).removeClass('unsplash-selected-image');
          $(this).find(".unsplash-image-check").prop('checked', false);
          $(this).removeClass('unsplash-configuration-error');
        }
        // If cardinality is 1 and click on different we switch selection
        else if (cardinality === 1) {
          var current_selection = $('.unsplash-image-wrapper.unsplash-selected-image');
          if (current_selection.length > 0) {
            current_selection.removeClass('unsplash-selected-image');
            current_selection.find(".unsplash-image-check").prop('checked', false);
            current_selection.removeClass('unsplash-configuration-error');
          }
          $(this).addClass('unsplash-selected-image');
          $(this).find(".unsplash-image-check").prop('checked', true);
        }
        // Clicked on unselected image need to check cardinality.
        else if (cardinality === -1 || $('.unsplash-image-wrapper.unsplash-selected-image').length < cardinality) {
          $(this).addClass('unsplash-selected-image');
          $(this).find(".unsplash-image-check").prop('checked', true);
        }
        if ($('.unsplash-image-wrapper.unsplash-selected-image').length > 0) {
          $("#edit-submit").show();
        } else {
          $("#edit-submit").hide();
        }
      });
      $(document).on(eventType, submitButton, function () {
        $(imageWrapper).removeClass('unsplash-configuration-error');
      });

      var searchBar = '#edit-search-key';
      $(document).on('keydown', searchBar, function (event) {
        if (event.keyCode == '13') {
          event.preventDefault();
          $("#edit-search").trigger('click');
        }
      });
    }
  };
})(Drupal, jQuery, drupalSettings);
