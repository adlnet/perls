/**
 * @file
 * Send statement for a user search.
 */

(function($, Drupal) {
  Drupal.behaviors.perlsXapiReportingSearch = {
    attach: function (context, settings) {
      $('.c-view-display-id--search_page .c-card a', context).once().on('click', function (element) {
        let cardId = $(this).closest('article').attr('node-id');
        let searched_text = $('.form-item-search-api-fulltext input').val();
        let searchData = {query: searched_text};
        sendLrsRequest(['xapi_selected'], cardId, searchData);
      });
    }
  };

  function sendLrsRequest(stateIds, content, requestData) {
    let data = {
      'state_ids': stateIds,
      'content': content,
      'extra_data': requestData,
    };

    $.ajax({
      url: Drupal.url('perls-xapi/send-report'),
      type: 'POST',
      dataType: 'json',
      data: JSON.stringify(data),
      success(results) {},
    });
  }

}(jQuery, Drupal));
