(function($, Drupal) {
  Drupal.behaviors.perlsManualCompleted = {
    attach: function (context, settings) {
      let startTime = Date.now();
      $('.completed-manually-button').once().on('click', function (event, element) {
        sendLrsRequest(startTime);
        $(event.currentTarget).find('.button').prop('disabled', true);
        $(event.currentTarget).find('.button').val(Drupal.t('Done'));
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
