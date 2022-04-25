(function($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.perlsLivePreview = {
    attach: function (context, settings) {
      $('.c-block-node-live-preview', context).once('perls-live-preview').each(function() {
        $('a', $(this)).attr({target: '_blank', rel: 'noopener'});

        $(document).ajaxStop(function() {
          var $target = $('a', '.c-block-node-live-preview');
          $target.attr({target: '_blank', rel: 'noopener'});

          $target.on('click', function (e) {
            var link = $(e.target).attr('href');

            if (link.length === 0) {
              e.preventDefault();
              alert(Drupal.t('This link will become active after you save your changes.'));
            }
          })
        });
      });
    },
  };
})(jQuery, Drupal, drupalSettings);
