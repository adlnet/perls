/**
 * All we do here is sending xapi statement.
 * When the user clicks on a download link of a certificate. We send a downloaded
 * statement.
 */

(function ($, Drupal) {
  Drupal.behaviors.BadgesXapiReportingDownload = {
    attach: function (context, settings) {
      $(window).once('badges_xapi_reporting').on('load', function () {
        $(".achievement.achievement-certificate.achievement-unlocked .achievement-links a")
          .once("badges_xapi_reporting")
          .on("click", function () {
            var achievement = $(this).closest('.achievement');
            if (achievement.length > 0) {
              var uuid = achievement.attr('uuid');
              var title = $('.achievement-title', achievement).text();
              sendLrsRequest(uuid, 'certificate', title);
            }

          });

      });
    }
  };

  function sendLrsRequest(uuid, type, title) {
    var activityurl = Drupal.url.toAbsolute('/achievement/' + uuid + '/' + type);
    let activityNameMapping = {};
    activityNameMapping[document.documentElement.lang] = title;
    var object = new ADL.XAPIStatement.Activity(activityurl, activityNameMapping);

    if (type == 'certificate') {
      object.definition['type'] = 'https://www.opigno.org/en/tincan_registry/activity_type/certificate';
      (new StatementBuilder())
        .setVerb('http://id.tincanapi.com/verb/viewed', 'viewed')
        .setObject(object)
        .sendStatement();
    }
    else {
      object.definition['type'] = 'http://activitystrea.ms/schema/1.0/badge';
      (new StatementBuilder())
        .setVerb('http://activitystrea.ms/schema/1.0/share', 'Shared')
        .setObject(object)
        .sendStatement();
    }
  }

}(jQuery, Drupal));
