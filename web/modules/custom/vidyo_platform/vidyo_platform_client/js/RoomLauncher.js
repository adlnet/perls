/**
 * @file
 * Launches Vidyo room in new window.
 */

 (function (Drupal, $) {
  Drupal.behaviors.vidyoRoomLauncher = {
    attach: function (context, settings) {
      $('a.vidyo--launch', context).once().click(function (event) {
        window.open(this.href, this.target, 'scrollbars');
        event.preventDefault();
      });
    }
  };
})(Drupal, jQuery);
