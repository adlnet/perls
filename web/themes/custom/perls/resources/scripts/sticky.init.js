/**
 * @file
 * Initialize sticky element behavior.
 * 
 * @see https://github.com/rgalus/sticky-js/ for Sticky-JS documentation and options.
 */

(function (Drupal, drupalSettings) {
    "use strict";
  
    Drupal.behaviors.perlsSticky = {
      attach: function (context, settings) {
        var body = document.querySelector('body:not([data-sticky-js-init])');
        if (body) {
          if (document.querySelector('body.l-page--manage-theme')) {
            var toolbar = document.querySelector('nav.toolbar-bar');
            var offset = (toolbar) ? toolbar.offsetHeight + 20 : 20;
            // Init a new "Sticky" class element for our color wheel.
            var stickyColorWheel = new Sticky('.color-placeholder .farbtastic', {
              'marginTop': offset,
              'stickyFor': 1100
            }); 
          }
          body.setAttribute('data-sticky-js-init', true);
        }
      },
    };
  })(Drupal, drupalSettings);
  