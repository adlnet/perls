(function ($, Drupal) {
  Drupal.behaviors.perlsTests = {
    attach: function (context, settings) {
      $(".perls-test", context)
        .once("sl-test")
        .each(function () {
          var $test = $(this);
          var correct = 0;

          // TODO: Use ajax for handling the selection of an option and re-rendering the results.
          $(".c-quiz__question", $test).click(function () {
            if ($(".o-icon--correct", $(this).next()).length > 0) {
              ++correct;
            }

            _updateResults();
          });

          function _updateResults() {
            $(".feedback .correct", $test).text(correct);
          }
        });
    },
  };
})(jQuery, Drupal);
