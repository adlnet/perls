/**
 * @file
 * Attaches show/hide functionality to checkboxes in the "Recommendation plugin" Settings page.
 */

(function ($) {
  "use strict";

  Drupal.behaviors.RecommmendationPlugins = {
    attach: function (context, settings) {
      $(
        ".recommendation-plugin-status-wrapper input.form-checkbox",
        context
      ).each(function () {
        var $checkbox = $(this);
        var plugin_id = $checkbox.data("id");

        var $rows = $(
          ".recommender-recommendation-plugin-weight--" + plugin_id,
          context
        );
        var tab = $(
          ".recommender-recommendation-plugin-settings-" + plugin_id,
          context
        ).data("verticalTab");

        // Bind a click handler to this checkbox to conditionally show and hide
        // the processor's table row and vertical tab pane.
        $checkbox.on("click.RecommendationUpdate", function () {
          if ($checkbox.is(":checked")) {
            $rows.show();
            if (tab) {
              tab.tabShow().updateSummary();
            }
          } else {
            $rows.hide();
            if (tab) {
              tab.tabHide().updateSummary();
            }
          }
        });

        // Trigger our bound click handler to update elements to initial state.
        $checkbox.triggerHandler("click.RecommendationUpdate");
      });
    },
  };
})(jQuery);
