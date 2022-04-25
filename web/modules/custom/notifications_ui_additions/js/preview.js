(function($, Drupal) {
  'use strict';

  Drupal.behaviors.previewNotificationRelatedContent = {
    attach: function(context) {
      $('form .push-notification-related-item a', context).attr('target', '_blank');
    },
  };

})(jQuery, Drupal);
