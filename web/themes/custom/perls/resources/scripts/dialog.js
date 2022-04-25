/**
 * @file
 * Custom JS for PERLS.
 */
(function($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Resolves this issue:
   * When JQuery dialog gets opened the focus goes to the first tabbable element inside the dialog's content
   * We dont want that, so instead we put the focus on the modal itself.
   */
  Drupal.behaviors.perlsDialog = {
    attach: function(context, settings) {
      var body = 'body';

      $(body).on('dialogfocus', function () {
        $('.ui-dialog', $(this)).focus();
      });

      $(body).once().on('dialogopen', function (event) {
        // Ref to the opened dialog.
        var openDialogContainer = (event.target.parentNode) ? $(event.target.parentNode) : null;
        $('body').on('click.hideMyDialog', function (event) {
          // If the click on the body is not on this specific overlay stop here.
          if (!event.target.classList.contains('ui-widget-overlay')) {
            return;
          }
          if (!$(event.target).closest('.ui-widget-content').length && !$(event.target).is('.ui-widget-content')) {
            // Close only 1 specific overlay if it exists.
            if (openDialogContainer) {
              var openDialogClose = openDialogContainer.find('.ui-dialog-titlebar-close');
              // Check if the expected close element was found.
              if (openDialogClose.length != 0) {
                openDialogClose.click();
                $('body').off('click.hideMyDialog');
                return;
              }
            }
            // Fallback closes every modal that is open.
            $('.ui-dialog-titlebar-close').click();
            $('body').off('click.hideMyDialog');
          }
        });
        $('html').addClass('stop-scroll');
      });

      $(body).on('dialogclose', function () {
        $('html').removeClass('stop-scroll');
      });

      $(body).once('renderComments').on('dialogopen', function () {
        // renderComments() is defined in web/modules/custom/react_comments
        if (typeof window.renderComments !== "undefined") {
          window.renderComments();
        }
      });
    },
  };

})(jQuery, Drupal, drupalSettings);
