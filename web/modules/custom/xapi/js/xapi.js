/**
 * @file
 * All we do here is set up an event listener for clicks on eLearning content.
 *
 * When the user opens the content, we stop the default behavior and open in a new window without toolbars, etc.
 */

(function ($, Drupal) {
  Drupal.behaviors.xapiBehaviors = {
    attach: function (context, settings) {
      // If this page contains an xAPI link, intercept clicks to open content in a window without chrome.
      // Global variable.
      var launchWindowObjectReference = null;
      $('.xapi-content.link').on('click', function (event) {
        // Prevent the default behavior (the content opens in a new tab).
        event.preventDefault();
        event.stopImmediatePropagation();

        // Get the URL that was clicked.
        const href = event.target.attributes['href'].value;

        // If the pointer to the window object in memory does not exist
        // or if such pointer exists but the window was closed.
        if (launchWindowObjectReference) {
          launchWindowObjectReference.close();
        }

        // Reopen it so it's brought back into focus.
        launchWindowObjectReference = window.open(href, 'launchWindow', 'menubar=0,toolbar=0,location=0,personalbar=0,status=0,scrollbars=1');
      });
    }
  };
}(jQuery, Drupal));
