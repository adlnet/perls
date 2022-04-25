/**
 * All we do here is sending xapi statement.
 * When the user opens node page then we are sending experience statement and
 * sending statment with completed when user scroll node page at bottom.
 */

(function ($, Drupal) {
  var scrollCount = 0;
  Drupal.behaviors.perlsXapiReportingArticleCompleted = {
    attach: function (context, settings) {
      $(window).once('xapi_reporting').on('load', function () {
        let onPageTime = Date.now();
        checkPosition(onPageTime);
        window.onscroll = function (e) {
          if (scrollCount === 0) checkPosition(onPageTime);
        }
      });
    }
  };

  function checkPosition(onPageTime) {
    var content;
    if ($('.field--name-field-body:last').length) {
      content = $('.field--name-field-body:last')
    } else if ($('.c-field--name-field-body:last').length) {
      content = $('.c-field--name-field-body:last')
    } else {
      console.log("Body content element not found. Unable to send completed statement.");
      return;
    }
    var bH = content.height();
    var bodyTopDis = content.offset().top;
    var wH = $(window).height();
    var wS = $(window).scrollTop();
    if (bH < (wS + (wH - bodyTopDis))) {
      scrollCount++;
      sendLrsRequest(onPageTime);
    }
  }

  function sendLrsRequest(openPageTime) {
    (new StatementBuilder())
      .setVerb(ADL.verbs.completed)
      .setDurationSince(openPageTime)
      .setCompletion(true)
      .sendStatement();
  }

}(jQuery, Drupal));
