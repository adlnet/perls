(function ($, Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.perlsClamp = {
    attach: function (context, settings) {
      $(".c-user-profile--card .c-field--name-field-name, .c-node--tile .field--name-title", context)
        .once("perls-clamp")
        .each(function (i, e) {
          $clamp(e, {clamp: 2, animate: true});
        });
      $(".c-node--card--course .c-field--name-field-description p, .c-node--card .c-field--name-field-description", context)
        .once("perls-clamp")
        .each(function (i, e) {
          // Add class to clamped course description element for CSS fallback.
          $(e).addClass('perls-clamp');
          $clamp(e, {clamp: 3, animate: true});
        });

      $(".card--description .c-field--name-field-description p", context)
        .once("perls-clamp")
        .each(function (i, e) {
          $clamp(e, {clamp: 12, animate: true});
        });
    },
  };
})(jQuery, Drupal, drupalSettings);
