(function ($, window, Drupal) {

  'use strict';

  /**
   * Drupal behavior to remove a log element when it is closed.
   *
   * @type {{attach: Function}}
   */
  Drupal.behaviors.renderNodeStats = {
    attach: function(context) {
      $('article.c-node--full--learn-article .view-comments', context).once('node_comments_stats').each(function () {
        renderStats();
        $(window).once('node_stats_dialog_close').on('dialog:afterclose', renderStats);
      });
    }
  };

  /**
   * Render comment stats.
   */
  const renderStats = function () {
    const nid = $("article.c-node--full--learn-article").attr("node-id");
    Drupal.ajax({
      url: Drupal.url("node/" + nid + "/render-stats"),
    }).execute();
  };


})(jQuery, window, Drupal);
