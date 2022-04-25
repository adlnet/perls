(function ($, Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.perlsStack = {
    attach: function (context, settings) {
      $(".c-stack", context)
        .once("perls-stack")
        .each(function () {
          var $stack = $(this);

          $(".stack-advance", $stack).click(_advance);
          $(".stack-restart", $stack).click(_restart);

          function _advance() {
            var next = $(".top", $stack).next();
            if (next.length == 0) {
              return;
            }

            $(".top", $stack).removeClass("top");
            next.addClass("top");
            $stack.trigger("afterStackAdvance");
          }
          function _restart() {
            var item = $(".c-field__item", $stack).first();
            $(".top", $stack).removeClass("top");
            item.addClass("top");
            $stack.trigger("onStackReset");
          }
        });
    },
  };
})(jQuery, Drupal, drupalSettings);
