/**
 * Implements preview feature for color module.
 **/

(function ($, Drupal, drupalSettings) {
  Drupal.color = {
    logoChanged: false,
    bgChanged: false,
    callback: function callback(context, settings, $form) {
      var $colorPalette = $form.find('.js-color-palette');
      var offsetLighter = 20;
      var offsetDarker = -20;

      /**
       * @variables {
       *   cssVariable: The css variable to update with the picked <color>.
       *   name: The input field to get the <color> from.
       *   offset: Amount for darken and lighten on the <color>. Positive value lightens, negative value darkens, the metric is percentage.
       * }
       */
      var selectors = [
        { cssVariable: '--primary-color', name: 'primary'},
        { cssVariable: '--secondary-color', name: 'secondary' },
        { cssVariable: '--secondary-lighter', name: 'secondary', offset: offsetLighter },
        { cssVariable: '--secondary-darker', name: 'secondary', offset: offsetDarker },
        { cssVariable: '--tertiary-color', name: 'tertiary'},
        { cssVariable: '--tip-color', name: 'tip'},
        { cssVariable: '--flashcard-color', name: 'flash'},
        { cssVariable: '--test-color', name: 'quiz'},
        { cssVariable: '--quiz-color', name: 'quiz'},
        { cssVariable: '--course-color', name: 'course'},
        { cssVariable: '--course-color-darker', name: 'course', offset: offsetDarker},
        { cssVariable: '--podcast-color', name: 'podcast'},
        { cssVariable: '--menu-background-color', name: 'menu'},
        { cssVariable: '--background-color', name: 'base'},
        { cssVariable: '--foreground-color', name: 'text'},
      ];

      /**
       * Credits to Crish Coyier at css-tricks.
       * @function: LightenDarkenColor(col, amt)
       */
      function shiftColor(col, amt) {

        var usePound = false;

        if (col[0] === "#") {
          col = col.slice(1);
          usePound = true;
        }

        var num = parseInt(col,16);

        var r = (num >> 16) + amt;

        if (r > 255) r = 255;
        else if (r < 0) r = 0;

        var b = ((num >> 8) & 0x00FF) + amt;

        if (b > 255) b = 255;
        else if (b < 0) b = 0;

        var g = (num & 0x0000FF) + amt;

        if (g > 255) g = 255;
        else if (g < 0) g = 0;

        [r, g, b] = [r,g,b].map(color => color <= 15 ? `0${color.toString(16)}` : color.toString(16));

        return (usePound?"#":"") + r + b + g;
      }

      for (var i = 0; i < selectors.length; i++){
        document.documentElement.style.setProperty(
          selectors[i].cssVariable,
          shiftColor(
            `${$colorPalette.find(`input[name="palette[${selectors[i].name}]"]`).val()}`,
            selectors[i].hasOwnProperty('offset') ? selectors[i].offset : 0,
          ),
        );
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
