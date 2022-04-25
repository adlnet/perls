/**
 * Update Course completions when you finish test.
 *
 */

(function ($, Drupal) {
  Drupal.behaviors.xapiAdaptiveContent = {
    attach: function (context, settings) {
      // Card stacks should listen for stack advance events.
      $(".c-stack")
        .once("adaptive")
        .on("afterStackAdvance", function (event) {
          refreshCourse($(this));
        });
    },
  };

  /**
   * Fetch the new course completions.
   */
  function refreshCourse(cstack) {
    // We only update the course completions when you complete the test.
    if ($(".results-card", cstack).parent().hasClass("top")) {
      // Update the course
      let course = $(cstack)
        .closest("article.c-node--full--course")
        .attr("node-id");

      Drupal.ajax({
        url: Drupal.url("adaptive/refresh_course/" + course),
      }).execute();
    }
  }
})(jQuery, Drupal);
