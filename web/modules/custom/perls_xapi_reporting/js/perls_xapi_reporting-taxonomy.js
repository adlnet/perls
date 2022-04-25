/**
 * Report an xAPI statement when a user views a taxonomy term.
 */

 (function ($, Drupal) {
  Drupal.behaviors.perlsXapiReportingTaxonomy = {
    attach: function (context, settings) {
      $(window).once('xapi_reporting').on('load', function () {
        (new StatementBuilder())
          .setVerb('http://id.tincanapi.com/verb/viewed', 'viewed')
          .sendStatement();
      });
    }
  };

}(jQuery, Drupal));
