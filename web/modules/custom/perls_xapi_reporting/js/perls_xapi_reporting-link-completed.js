/**
 * All we do here is sending xapi statement.
 * When the user clicks on a link in a link article. We send a completed
 * statement.
 */

(function ($, Drupal) {
  var scrollCount = 0;
  Drupal.behaviors.perlsXapiReportingLinkCompleted = {
    attach: function (context, settings) {
      $(window).once('xapi_reporting').on('load', function () {
        let onPageTime = Date.now();

        $("article.c-node--learn-link.c-node--full .c-node__content a.o-button")
          .once("xapi_reporting")
          .on("click", function () {
            sendLrsRequest(onPageTime);
          });

      });
    }
  };

  function sendLrsRequest(openPageTime) {
    (new StatementBuilder())
      .setVerb(ADL.verbs.completed)
      .setDurationSince(openPageTime)
      .sendStatement();
  }

}(jQuery, Drupal));
