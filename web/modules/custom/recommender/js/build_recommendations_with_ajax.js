(function ($, Drupal) {
    Drupal.behaviors.RecommendationAjax = {
        attach: function (context, settings) {
            // Check the expected view is on this page.
            if ($(".recommendation-ajax-view").length > 0) {
                $(document)
                    .once('recommendation_ajax')
                    .each(function (element) {
                        var target = Drupal.url('recommender/get_recommendations');
                        Drupal.ajax({ url: target }).execute();
                    });
            }
        },
    };
})(jQuery, Drupal);