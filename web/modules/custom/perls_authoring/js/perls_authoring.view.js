/**
 * @file
 * Defines the behavior of the media entity browser view.
 */

(function ($) {

  "use strict";

  /**
   * Attaches the behavior of the media entity browser view.
   */
  Drupal.behaviors.perlsAuthoringView = {
    attach: function (context, settings) {
      if ($('.contain-selectable-element') === undefined) {
        return;
      }
      // Hide the Insert selected button in every page load.
      $('.is-entity-browser-submit').hide();
      // Add a checked class when clicked.
      $(document, context).once('submitButton').click(function () {
        if ($('.contain-selectable-element .views-row', context) === undefined) {
          return;
        }
        var $selected = $('.contain-selectable-element', context).find('.views-row.checked');
        if ($selected.length) {
          $('.is-entity-browser-submit').show();
        }
      });
    }
  };

}(jQuery, Drupal));
