(function ($, window, Drupal) {
  'use strict';
  /**
   * Drupal behavior to append the block via Ajax.
   *
   * @type {{attach: Function}}
   */
  Drupal.behaviors.renderNextCourseBlock = {
    attach: function(context) {
      var selector = "article.c-node--full--learn-article";
      const nid = $(selector).attr("node-id");
      var data = {
        "plugin_id": "next_course_content_block",
        "config": {
          "nid": nid,
        },
        "selector": selector,
      };
      var json_data = JSON.stringify(data);

      $(data.selector, context).once('block_via_ajax').each(function () {
        renderBlockViaAjax(json_data);
      });
    }
  };

  /**
   * Render block via Ajax.
   */
  const renderBlockViaAjax = function (json_data) {
    Drupal.ajax({
      url: Drupal.url("block-ajax-load?data=" + json_data),
    }).execute();
  };
})(jQuery, window, Drupal);
