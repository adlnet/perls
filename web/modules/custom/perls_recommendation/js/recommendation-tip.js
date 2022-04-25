(function($, Drupal) {
  Drupal.behaviors.perlsRecommendationTip = {
    attach: function (context, settings) {
      $('.recommendation-info-trigger').once().on('click', function (element) {
        $('.page-overlay').show();

        // Prevents to close the overlay.
        $('.message-box').once().on('click', function (event) {
          event.stopPropagation();
        });

        // Click outside of message box.
        $('.page-overlay').once().on('click', function () {
          closeOverlay();
        });
        let tipText = $(this).next('.recommendation-info-content').html();
        $('.page-overlay .message-box-content').append(tipText);
      });

      // Click to close button.
      $('.box-close-icon').once().on('click', function (element) {
        closeOverlay();
      });
    }
  };

  function closeOverlay() {
    $('.page-overlay').hide();
    $('.page-overlay .message-box-content').html('');
  }
}(jQuery, Drupal));
