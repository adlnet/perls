(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.PromptsXapiReport = {
    attach: function (context, settings) {
      $(window).on('dialog:afterclose', (e) => {
        location.reload();
      });
    }

  };
})(jQuery, Drupal);
