/**
 * All we do here is sending xapi statement.
 * When the user clicks on a link in a learn file article. We send a completed
 * statement.
 */

(function ($, Drupal) {
  Drupal.behaviors.perlsXapiReportingFileCompleted = {
    attach: function (context, settings) {
      $(window).once('xapi_reporting').on('load', function () {
        let onPageTime = Date.now();

        $("article.c-node--learn-file.c-node--full .c-node__content .c-field--name-field-document a")
          .once("xapi_reporting")
          .on("click", function () {
            sendLrsRequest(onPageTime);
          });
        // Cards don't have an xapi template so we have to send via state interface.
        $("article.c-node--learn-file.c-node--card .c-node__content .c-field--name-field-document a")
          .once("xapi_reporting")
          .on("click", function () {
            var node_id = $(this).closest("article").attr("node-id");
            sendLrsStateRequest(['xapi_completed_state'], node_id, {});
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

  /**
   * A convience function to run both packet data and send data.
   **/
  function sendLrsStateRequest(stateIds, content, requestData) {
    let data = packetLrsData(stateIds, content, requestData);
    sendLrsArray(data);
  }

  /**
   * Create a clean json object out of the xapi data.
   **/
  function packetLrsData(stateIds, content, requestData) {
    return {
      state_ids: stateIds,
      content: content,
      extra_data: requestData,
    };
  }

  /**
   * Ajax call to push the xapi data to server.
   **/
  function sendLrsArray(data) {
    $.ajax({
      url: Drupal.url("perls-xapi/send-report"),
      type: "POST",
      dataType: "json",
      data: JSON.stringify(data),
      success(results) { },
    });
  }

}(jQuery, Drupal));
